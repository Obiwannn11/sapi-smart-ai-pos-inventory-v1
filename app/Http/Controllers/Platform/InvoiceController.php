<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platform\InvoiceResource;
use App\Models\Invoice;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tagihan & pembayaran — dicatat MANUAL di v1, belum ada payment gateway.
 *
 * Setiap tindakan yang mengubah keadaan dicatat sebagai kejadian `sensitive`.
 * Panel ini bisa menulis (menerbitkan tagihan, menerima/menolak bukti bayar),
 * dan panel yang bisa menulis tanpa jejak audit tidak bisa dipertanggungjawabkan
 * kepada klien.
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): Response
    {
        $filter = $request->string('status')->toString();

        $invoices = Invoice::query()
            ->with(['tenant:id,name,status', 'verifier:id,name'])
            ->when($filter !== '', fn ($query) => $query->where('status', $filter))
            // Yang menunggu diperiksa naik ke atas: itulah satu-satunya baris
            // di halaman ini yang menunggu tindakan seseorang.
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [Invoice::STATUS_AWAITING_VERIFICATION])
            ->orderByDesc('period')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        PlatformAuditLog::recordRoutine('invoices.index');

        return Inertia::render('Platform/Invoices/Index', [
            'invoices' => InvoiceResource::collection($invoices),
            'filters' => ['status' => $filter],
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => [
                Invoice::STATUS_UNPAID,
                Invoice::STATUS_AWAITING_VERIFICATION,
                Invoice::STATUS_PAID,
                Invoice::STATUS_REJECTED,
            ],
        ]);
    }

    /**
     * Terbitkan tagihan untuk satu tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', Rule::exists('tenants', 'id')],
            // Format YYYY-MM. Dipakai sebagai kunci unik bersama tenant_id,
            // sehingga satu tenant tidak bisa ditagih dua kali untuk bulan yang
            // sama karena salah klik.
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
        ]);

        $subscription = Subscription::where('tenant_id', $validated['tenant_id'])->first();

        if ($subscription === null) {
            return back()->with('error', 'Tenant ini belum punya langganan.');
        }

        $sudahAda = Invoice::where('tenant_id', $validated['tenant_id'])
            ->where('period', $validated['period'])
            ->exists();

        if ($sudahAda) {
            return back()->with('error', "Tagihan periode {$validated['period']} untuk tenant ini sudah ada.");
        }

        $invoice = Invoice::create([
            'tenant_id' => $validated['tenant_id'],
            'subscription_id' => $subscription->id,
            'period' => $validated['period'],
            'amount' => $validated['amount'],
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => $validated['due_date'],
        ]);

        PlatformAuditLog::record('invoices.create', $invoice, [
            'tenant_id' => $invoice->tenant_id,
            'period' => $invoice->period,
            'amount' => (float) $invoice->amount,
        ]);

        return back()->with('success', 'Tagihan diterbitkan.');
    }

    /**
     * Terima bukti bayar: tagihan lunas, tenant kembali aktif, periode maju.
     */
    public function verify(Request $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->isPaid()) {
            return back()->with('error', 'Tagihan ini sudah lunas.');
        }

        $invoice->update([
            'status' => Invoice::STATUS_PAID,
            'paid_at' => now(),
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);

        $subscription = $invoice->subscription;

        if ($invoice->isUpgrade()) {
            // Upgrade hanya menambah seat. Ia TIDAK memperpanjang periode dan
            // TIDAK mengubah tarif bulanan — biaya sekali-bayar untuk kasir
            // tambahan bukan harga langganan, dan menukar keduanya akan membuat
            // tagihan bulan depan salah.
            if ($invoice->grants_seats !== null) {
                $subscription->update(['seats' => $invoice->grants_seats]);
            }
        } else {
            $periodStart = now()->startOfDay();

            $subscription->update([
                // Harga DIKUNCI dari nominal yang benar-benar dibayar, bukan
                // dibaca ulang dari tabel tarif. Inilah grandfathering:
                // mengubah tarif besok tidak boleh mengubah apa yang sudah
                // disepakati hari ini.
                'price_locked' => $invoice->amount,
                'current_period_start' => $periodStart->toDateString(),
                'current_period_end' => $periodStart->copy()->addMonth()->toDateString(),
                // Puncak seat direset di awal periode baru — ia mengukur
                // pemakaian periode berjalan, bukan sepanjang masa.
                'seat_high_water' => $subscription->activeSeatsUsed(),
            ]);

            $invoice->tenant->update(['status' => Tenant::STATUS_ACTIVE]);
        }

        PlatformAuditLog::record('invoices.verify', $invoice, [
            'tenant_id' => $invoice->tenant_id,
            'period' => $invoice->period,
            'amount' => (float) $invoice->amount,
        ]);

        return back()->with('success', 'Pembayaran diterima, langganan tenant diaktifkan.');
    }

    /**
     * Tolak bukti bayar.
     *
     * Sengaja TIDAK menyentuh status tenant maupun akun stafnya. Menonaktifkan
     * akun yang sedang dipakai bekerja jauh lebih merusak kepercayaan daripada
     * menahan penambahan berikutnya — dan tenant yang buktinya ditolak biasanya
     * salah unggah, bukan menipu.
     */
    public function reject(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($invoice->isPaid()) {
            return back()->with('error', 'Tagihan yang sudah lunas tidak bisa ditolak.');
        }

        $invoice->update([
            'status' => Invoice::STATUS_REJECTED,
            'rejection_reason' => $validated['reason'],
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            // Tenggang 3×24 jam untuk memperbaiki. Tenant yang salah unggah
            // butuh waktu memperbaikinya, bukan hukuman seketika.
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->subscriptions->revertUpgrade($invoice);

        PlatformAuditLog::record('invoices.reject', $invoice, [
            'tenant_id' => $invoice->tenant_id,
            'period' => $invoice->period,
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', 'Bukti bayar ditolak, tenant akan diberi tahu.');
    }

    /**
     * Buka berkas bukti transfer.
     *
     * Disimpan di disk privat, jadi hanya bisa dibaca lewat rute ini — yang
     * digerbang `platform.can:payments`. Bukti transfer memuat nama dan nomor
     * rekening; URL yang bisa ditebak siapa saja bukan tempatnya.
     */
    public function proof(Invoice $invoice): StreamedResponse
    {
        abort_if($invoice->proof_path === null, 404);
        abort_unless(Storage::disk('local')->exists($invoice->proof_path), 404);

        PlatformAuditLog::record('invoices.proof.view', $invoice, [
            'tenant_id' => $invoice->tenant_id,
            'period' => $invoice->period,
        ]);

        return Storage::disk('local')->response($invoice->proof_path);
    }
}

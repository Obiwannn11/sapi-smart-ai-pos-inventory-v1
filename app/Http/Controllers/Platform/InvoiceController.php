<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platform\InvoiceResource;
use App\Models\Invoice;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\PricingService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PricingService $pricing,
    ) {}

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
     * Nominal yang disarankan aturan harga untuk satu tenant & periode.
     *
     * Sengaja mengembalikan HANYA tarif dan nama kelompoknya. Konteks yang
     * menghasilkannya memuat omzet dan cacah transaksi — data bisnis, yang
     * jalur sahnya cuma halaman omzet beraudit. Nominal dan label kelompok
     * setara dengan yang sudah terlihat di daftar tenant sejak Tahap C, jadi
     * tidak ada yang terbuka lebih lebar di sini.
     */
    public function suggestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', Rule::exists('tenants', 'id')],
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);
        $resolved = $this->pricing->resolveFor($tenant, $this->periodStart($validated['period']));

        return response()->json([
            'amount' => $resolved['price'],
            'label' => $resolved['label'],
            // `source` membedakan tarif yang keluar dari aturan dari tarif yang
            // keluar karena TIDAK ada aturan yang cocok. Keduanya kini berupa
            // angka, dan formulir tagihan harus mengatakan yang mana — angka
            // paket penampung yang disodorkan seolah hasil aturan adalah
            // kekeliruan yang baru ketahuan saat tenant bertanya.
            'source' => $resolved['source'],
            'matched' => $resolved['rule'] !== null,
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

        $resolved = $this->pricing->resolveFor(
            $subscription->tenant,
            $this->periodStart($validated['period']),
        );

        // Nominalnya tetap milik pemilik SaaS — aturan menyarankan, ia yang
        // memutuskan. Karena itu aturannya dicatat HANYA bila nominal yang
        // terbit benar-benar sama dengan tarif aturannya. Menautkan aturan pada
        // nominal yang diketik ulang akan melahirkan jejak yang berbohong:
        // seolah harga itu keluar dari aturan, padahal aturannya ditolak.
        $mengikutiAturan = $resolved['rule'] !== null
            && abs((float) $validated['amount'] - $resolved['price']) < 0.01;

        $invoice = Invoice::create([
            'tenant_id' => $validated['tenant_id'],
            'subscription_id' => $subscription->id,
            'period' => $validated['period'],
            'amount' => $validated['amount'],
            'pricing_rule_id' => $mengikutiAturan ? $resolved['rule']->id : null,
            // Konteksnya disimpan apa pun keputusan nominalnya. Justru saat
            // pemilik SaaS menyimpang dari aturan, "keadaan tenant seperti apa
            // waktu itu" adalah pertanyaan yang paling mungkin ditanyakan.
            'pricing_context' => $resolved['context'],
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => $validated['due_date'],
        ]);

        PlatformAuditLog::record('invoices.create', $invoice, [
            'tenant_id' => $invoice->tenant_id,
            'period' => $invoice->period,
            'amount' => (float) $invoice->amount,
            'pricing_rule' => $resolved['label'],
            'follows_rule' => $mengikutiAturan,
        ]);

        return back()->with('success', 'Tagihan diterbitkan.');
    }

    /**
     * Awal bulan periode tagihan, sebagai titik waktu penetapan harga.
     *
     * Awal periode, bukan akhirnya: aturan yang mulai berlaku di tengah bulan
     * tidak boleh mengubah harga bulan yang sudah berjalan. Yang dipakai adalah
     * aturan yang sudah berdiri saat periodenya dibuka — itulah yang akan
     * dikatakan kepada tenant bila ia bertanya.
     */
    protected function periodStart(string $period): Carbon
    {
        return Carbon::createFromFormat('Y-m', $period)->startOfMonth();
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

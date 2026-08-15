<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platform\SubscriptionResource;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PricingService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Langganan & tagihan seluruh klien — satu bagian, bukan dua.
 *
 * Dulu keduanya berdiri sendiri: satu halaman menampilkan paket dan seat, satu
 * lagi menampilkan tagihan, dan tak satu pun menaut ke yang lain. Akibatnya
 * pertanyaan yang paling sering diajukan — "kenapa tenant ini ditangguhkan?" —
 * hanya bisa dijawab dengan membuka dua halaman lalu mencocokkan namanya
 * sendiri. Keduanya adalah urusan yang sama dilihat dari dua sisi.
 *
 * Hanya keterangan komersial — paket, tarif, seat, periode, tagihan. Model
 * operasional tenant TIDAK boleh diimpor di sini; ditegakkan PlatformArchTest.
 *
 * Rincian per akun sudah pindah ke `TenantController::show()`: ia menjawab
 * pertanyaan tentang satu tenant, bukan tentang satu langganan, dan menaruhnya
 * di modul langganan membuat daftar tenant menaut ke halaman yang sebagian staf
 * tidak berhak membukanya. Yang tinggal di sini adalah daftarnya dan satu
 * tindakan tulis atas langganan.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request, PricingService $pricing): Response
    {
        $viewer = $request->user();
        $bolehTagihan = $viewer->hasModule('payments');
        $bolehOmzet = $viewer->hasModule('revenue_data');

        $filter = $request->string('status')->toString();

        PlatformAuditLog::recordRoutine('subscriptions.index');

        // Satu daftar, empat prop ([BL-037]). Daftar langganan dan ketiga peta
        // pendampingnya diturunkan dari halaman baris yang sama, jadi keempatnya
        // ditunda dalam satu grup dan berbagi satu hasil yang dihitung sekali —
        // empat closure yang masing-masing mengulang kuerinya akan membuat
        // penundaan ini justru lebih mahal daripada tidak menunda sama sekali.
        $daftar = null;
        $resolveDaftar = function () use (&$daftar, $filter, $bolehTagihan, $bolehOmzet, $pricing): array {
            if ($daftar !== null) {
                return $daftar;
            }

            $subscriptions = Subscription::query()
                ->with(['tenant:id,name,slug,status', 'plan:id,name'])
                ->join('tenants', 'tenants.id', '=', 'subscriptions.tenant_id')
                ->when($filter !== '', fn ($query) => $query->where('tenants.status', $filter))
                ->orderBy('tenants.name')
                ->select('subscriptions.*')
                ->paginate(25)
                ->withQueryString();

            // Dihitung SEBELUM resource dirakit, dan urutannya bukan selera:
            // `JsonResource::collection()` mengganti isi paginator dengan resource,
            // sehingga apa pun yang menyentuh modelnya setelah itu menerima
            // pembungkusnya, bukan model yang diharapkan.
            $rows = $subscriptions->getCollection();
            $tenantIds = $rows->pluck('tenant_id')->all();

            $seatUsage = $this->seatUsageFor($tenantIds);
            $billing = $bolehTagihan ? $this->billingFor($tenantIds) : [];
            // Kelompok harga (LABEL, bukan rupiah) hanya dihitung bila pengguna
            // memang berizin melihat data omzet. Untuk yang tidak, kuncinya kosong
            // sama sekali — bukan terisi lalu disembunyikan di Vue.
            $brackets = $bolehOmzet ? $this->bracketsFor($rows, $pricing) : [];

            return $daftar = [
                'subscriptions' => SubscriptionResource::collection($subscriptions),
                'seat_usage' => $seatUsage,
                'billing' => $billing,
                'brackets' => $brackets,
            ];
        };

        return Inertia::render('Platform/Subscriptions/Index', [
            'subscriptions' => Inertia::defer(fn () => $resolveDaftar()['subscriptions'], 'daftar'),
            'filters' => ['status' => $filter],
            'statuses' => [
                Tenant::STATUS_TRIAL,
                Tenant::STATUS_ACTIVE,
                Tenant::STATUS_GRACE,
                Tenant::STATUS_SUSPENDED,
            ],
            // Peta per tenant, bukan kolom di resource. Angka-angka ini dihitung
            // untuk halaman ini saja dan tidak termasuk bentuk sebuah langganan
            // — menaruhnya di daftar putih resource akan membuatnya ikut terbawa
            // ke tempat yang tak pernah memintanya.
            'seat_usage' => Inertia::defer(fn () => $resolveDaftar()['seat_usage'], 'daftar'),
            'billing' => Inertia::defer(fn () => $resolveDaftar()['billing'], 'daftar'),
            // Ringkasan tagihan tetap eager: dua cacah pendek, dan justru
            // inilah angka yang dicari saat halaman ini dibuka.
            'summary' => $bolehTagihan ? $this->billingSummary() : null,
            'brackets' => Inertia::defer(fn () => $resolveDaftar()['brackets'], 'daftar'),
            'can' => [
                'subscriptions' => $viewer->hasModule('subscriptions'),
                'payments' => $bolehTagihan,
                'revenue' => $bolehOmzet,
            ],
        ]);
    }

    /**
     * Ubah batas pengguna satu langganan.
     *
     * Sampai sekarang batas ini hanya bisa bergerak lewat tagihan penambahan
     * yang dibayar tenant — jalur yang benar untuk keadaan biasa, tapi tidak
     * punya jawaban untuk keadaan yang tersisa: salah pilih jumlah, kesepakatan
     * di luar aplikasi, koreksi setelah bukti bayar ditolak. Tanpa jalan itu,
     * satu-satunya penyelesaian adalah menyunting database.
     *
     * `reason` wajib. Batas seat yang berubah tanpa alasan tertulis adalah
     * persis hal yang tidak bisa dijelaskan saat tenant menanyakannya, dan
     * angka sebelum-sesudah saja tidak menjawab "kenapa".
     *
     * Turun di bawah pemakaian aktif DIIZINKAN, dan tidak ada akun yang
     * dinonaktifkan karenanya — mengikuti alasan yang sama seperti penolakan
     * bukti bayar: yang tertutup adalah penambahan berikutnya, bukan pekerjaan
     * orang yang sedang berjalan.
     */
    public function updateSeats(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'seats' => ['required', 'integer', 'min:1', 'max:1000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $sebelum = $subscription->seats;

        if ($sebelum === $validated['seats']) {
            return back()->with('error', 'Batas penggunanya sudah bernilai itu.');
        }

        $subscription->update(['seats' => $validated['seats']]);

        PlatformAuditLog::record('subscriptions.seats.update', $subscription, [
            'tenant_id' => $subscription->tenant_id,
            'before' => $sebelum,
            'after' => $subscription->seats,
            'active_users' => $subscription->activeSeatsUsed(),
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', "Batas pengguna diubah dari {$sebelum} menjadi {$subscription->seats}.");
    }

    /**
     * Pindahkan satu tenant ke paket lain.
     *
     * Ini tindakan pemilik SaaS, bukan permintaan pemilik toko, dan urutan itu
     * disengaja (`[BL-046]`(d)): pemindahan ke Premium karena omsetnya melewati
     * batas jalur Adaptif adalah keputusan penyedia layanan. Permintaan naik
     * paket mandiri oleh owner boleh menyusul lewat pola `Invoice` `KIND_UPGRADE`
     * yang sudah ada, tapi tidak boleh menjadi satu-satunya jalan — kalau tidak,
     * keputusan yang menolak seseorang bersandar pada persetujuan orang itu
     * sendiri.
     *
     * `reason` wajib, dengan alasan yang sama seperti pada batas pengguna:
     * paket menentukan tarif, jatah seat, dan kuota AI sekaligus. Perpindahan
     * tanpa alasan tertulis adalah tiga perubahan yang tak satu pun bisa
     * dijelaskan saat tenant menanyakannya.
     */
    public function updatePlan(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            // Hanya paket yang masih aktif. Memindahkan tenant ke paket yang
            // sudah dihentikan berarti menaruhnya di tarif yang tidak lagi
            // ditawarkan kepada siapa pun.
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $subscription->loadMissing('plan');

        if ($subscription->plan_id === (int) $validated['plan_id']) {
            return back()->with('error', 'Tenant ini sudah berada di paket tersebut.');
        }

        $plan = Plan::findOrFail($validated['plan_id']);
        $sebelum = $this->planSnapshot($subscription);

        $this->subscriptions->changePlan($subscription, $plan);

        PlatformAuditLog::record('subscriptions.plan.update', $subscription, [
            'tenant_id' => $subscription->tenant_id,
            'before' => $sebelum,
            'after' => $this->planSnapshot($subscription),
            'reason' => $validated['reason'],
        ]);

        return back()->with(
            'success',
            "{$sebelum['plan']} → {$plan->name}. Tarif periode berjalan tidak berubah; paket barunya berlaku pada tagihan berikutnya.",
        );
    }

    /**
     * Keadaan paket sebuah langganan yang layak muncul di jejak audit.
     *
     * Batas pengguna dan kuota AI ikut, bukan hanya nama paketnya: satu
     * perpindahan mengubah ketiganya sekaligus, dan "dipindah ke Premium" tidak
     * menjawab pertanyaan yang benar-benar diajukan tenant — kenapa batas
     * penggunanya berubah, dan kenapa analisis AI-nya tiba-tiba ditolak.
     *
     * @return array<string, mixed>
     */
    private function planSnapshot(Subscription $subscription): array
    {
        $plan = $subscription->plan;

        return [
            'plan' => $plan?->name,
            'plan_id' => $subscription->plan_id,
            'base_price' => $plan === null ? null : (float) $plan->base_price,
            'seats' => $subscription->seats,
            'included_seats' => $plan?->included_seats,
            'ai_daily_limit' => $plan?->limit(Plan::LIMIT_AI_DAILY),
        ];
    }

    /**
     * Jumlah pengguna aktif per tenant, dalam SATU query.
     *
     * `Subscription::activeSeatsUsed()` menghitungnya per baris — benar untuk
     * satu langganan, tapi 25 query untuk satu halaman daftar.
     *
     * @param  array<int, int>  $tenantIds
     * @return array<int, int>
     */
    private function seatUsageFor(array $tenantIds): array
    {
        return User::query()
            ->whereIn('tenant_id', $tenantIds)
            ->where('is_active', true)
            ->selectRaw('tenant_id, COUNT(*) as jumlah')
            ->groupBy('tenant_id')
            ->pluck('jumlah', 'tenant_id')
            ->all();
    }

    /**
     * Keadaan tagihan per tenant: berapa yang terbuka, dan mana yang menunggu.
     *
     * @param  array<int, int>  $tenantIds
     * @return array<int, array{open: int, awaiting: int, overdue: int}>
     */
    private function billingFor(array $tenantIds): array
    {
        return Invoice::query()
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_AWAITING_VERIFICATION])
            ->get(['tenant_id', 'status', 'due_date'])
            ->groupBy('tenant_id')
            ->map(fn ($invoices) => [
                'open' => $invoices->count(),
                'awaiting' => $invoices->where('status', Invoice::STATUS_AWAITING_VERIFICATION)->count(),
                'overdue' => $invoices->filter(fn (Invoice $invoice) => $invoice->due_date?->isPast() ?? false)->count(),
            ])
            ->all();
    }

    /**
     * Dua angka yang menentukan apakah halaman ini perlu dibuka hari ini.
     *
     * @return array{awaiting: int, overdue: int}
     */
    private function billingSummary(): array
    {
        return [
            'awaiting' => Invoice::where('status', Invoice::STATUS_AWAITING_VERIFICATION)->count(),
            'overdue' => Invoice::whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_AWAITING_VERIFICATION])
                ->whereDate('due_date', '<', now()->toDateString())
                ->count(),
        ];
    }

    /**
     * Kelompok harga per tenant jalur adaptif — LABEL saja, tanpa angka rupiah.
     *
     * Membuka daftar ini tidak dicatat sebagai kejadian sensitif justru karena
     * angkanya tidak ikut terkirim. Yang tercatat adalah membuka rinciannya,
     * di RevenueController.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Subscription>  $subscriptions
     * @return array<int, string>
     */
    private function bracketsFor($subscriptions, PricingService $pricing): array
    {
        return $subscriptions
            ->filter(fn (Subscription $subscription) => $subscription->isSubsidized())
            ->mapWithKeys(function (Subscription $subscription) use ($pricing) {
                $bracket = $pricing->currentBracketFor($subscription->tenant);

                return [$subscription->tenant_id => $bracket['label'] ?? '—'];
            })
            ->all();
    }
}

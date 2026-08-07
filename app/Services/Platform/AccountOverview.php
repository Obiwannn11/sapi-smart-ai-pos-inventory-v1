<?php

namespace App\Services\Platform;

use App\Http\Resources\Platform\InvoiceResource;
use App\Models\Plan;
use App\Models\PlatformUser;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\PricingService;

/**
 * Rincian satu akun klien sebagaimana boleh dilihat pemilik SaaS.
 *
 * Tinggal di service, bukan di controller, karena DUA rute merakit halaman yang
 * sama: rincian akun biasa, dan rincian akun yang sekaligus membuka omzetnya.
 * Yang kedua digerbang modul sendiri dan SELALU tercatat di jejak audit —
 * memisahkan rutenya adalah caranya, dan menyalin payload-nya ke dua controller
 * adalah cara termudah membuat keduanya pelan-pelan berbeda isi.
 *
 * Tiap bagian digerbang modul yang dipegang penglihatnya, bukan disaring di
 * Vue. Data yang tidak boleh dilihat tidak ikut terkirim sama sekali — bukan
 * terkirim lalu disembunyikan.
 */
class AccountOverview
{
    /**
     * Kapabilitas kasir yang boleh diketahui pemilik SaaS, beserta bunyinya di
     * layar. Kuncinya HARUS dikenali `Tenant::hasFeature()` — nama yang salah
     * ketik akan tampil "tidak aktif" alih-alih meledak, jadi menambah baris di
     * sini selalu berpasangan dengan menambah cabang di sana.
     *
     * @var array<string, array{label: string, description: string}>
     */
    private const CAPABILITIES = [
        'kitchen_queue' => [
            'label' => 'Papan antrian dapur',
            'description' => 'Pesanan yang masuk tampil di layar dapur dan bisa ditandai selesai dari sana.',
        ],
        'self_order' => [
            'label' => 'Pesan mandiri',
            'description' => 'Pembeli memesan sendiri lewat tautan meja, tanpa mengantre di kasir.',
        ],
        'ai' => [
            'label' => 'Analisis AI',
            'description' => 'Ringkasan penjualan dan saran jual yang dihitung asisten, di halaman pemilik toko.',
        ],
    ];

    public function __construct(private readonly PricingService $pricing) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Tenant $tenant, PlatformUser $viewer): array
    {
        $bolehLangganan = $viewer->hasModule('subscriptions');
        $bolehTagihan = $viewer->hasModule('payments');
        $bolehOmzet = $viewer->hasModule('revenue_data');

        $tenant->loadMissing('owners:id,tenant_id,name,email,email_verified_at');
        $tenant->loadCount('users');

        return [
            'tenant' => $this->tenantPayload($tenant),
            'subscription' => $bolehLangganan ? $this->subscriptionPayload($tenant) : null,
            // Katalog paket, untuk form perpindahan. Ikut hanya bagi pemegang
            // modul langganan — yang tidak boleh memindahkan tenant tidak perlu
            // menerima daftar tujuannya.
            'plans' => $bolehLangganan ? $this->planCatalog() : null,
            'invoices' => $bolehTagihan ? $this->invoicePayload($tenant) : null,
            // Kelompok harga adalah LABEL, bukan angka rupiah — setara dengan
            // yang sudah tampil di daftar. Angka omzetnya hidup di rute
            // tersendiri yang tercatat tiap kali dibuka.
            'bracket' => $bolehOmzet && $tenant->pricing_track === Subscription::TRACK_SUBSIDIZED
                ? $this->pricing->currentBracketFor($tenant)['label'] ?? null
                : null,
            'can' => [
                // Ikut dikirim karena halamannya perlu tahu ke daftar mana
                // pembacanya boleh dikembalikan. Tombol "kembali" yang menunjuk
                // halaman terlarang adalah bentuk kecil dari kekeliruan yang
                // sama seperti yang membuat rincian ini pindah ke sini.
                'tenants' => $viewer->hasModule('tenants'),
                'subscriptions' => $bolehLangganan,
                'payments' => $bolehTagihan,
                'revenue' => $bolehOmzet,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantPayload(Tenant $tenant): array
    {
        $owner = $tenant->owners->first();

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'business_type' => $tenant->business_type,
            'business_type_label' => config("pricing-dimensions.business_type.options.{$tenant->business_type}"),
            'registered_at' => $tenant->created_at?->toDateString(),
            'user_count' => $tenant->users_count,
            'flagged_at' => $tenant->flagged_at?->toDateString(),
            'flag_reason' => $tenant->flag_reason,
            'is_verified' => $owner?->hasVerifiedEmail() ?? false,
            'owner' => $owner === null ? null : [
                'name' => $owner->name,
                'email' => $owner->email,
            ],
            'capabilities' => $this->capabilityPayload($tenant),
        ];
    }

    /**
     * Fitur kasir yang menyala untuk tenant ini — MEMBACA saja.
     *
     * Tidak ada jalan mengubahnya dari panel platform, dan itu keputusan yang
     * sama dengan pencabutan kuasa mengubah tipe usaha: cara kerja usaha orang
     * bukan milik penyedia layanan. Yang diberikan halaman ini adalah kuasa
     * mengetahui — pemilik SaaS tetap perlu tahu fitur apa yang aktif saat
     * menjawab keluhan atau menjelaskan tarif.
     *
     * Dibaca lewat `hasFeature()`, bukan kolomnya langsung, supaya menambah
     * fitur kelak tetap satu tempat.
     *
     * @return array<int, array{key: string, label: string, description: string, enabled: bool}>
     */
    private function capabilityPayload(Tenant $tenant): array
    {
        return collect(self::CAPABILITIES)
            ->map(fn (array $keterangan, string $key) => [
                'key' => $key,
                ...$keterangan,
                'enabled' => $tenant->hasFeature($key),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function subscriptionPayload(Tenant $tenant): ?array
    {
        $subscription = $tenant->subscription()->with('plan')->first();

        if ($subscription === null) {
            return null;
        }

        return [
            'id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'plan_name' => $subscription->plan?->name,
            'plan_base_price' => $subscription->plan === null
                ? null
                : (float) $subscription->plan->base_price,
            // Dikirim apa adanya, `null` berarti paket ini tidak menyetel
            // batasnya sendiri — bedanya harus tetap terlihat di layar, persis
            // seperti di halaman Aturan Harga.
            'plan_ai_daily_limit' => $subscription->plan?->limit(Plan::LIMIT_AI_DAILY),
            // Angka inilah yang menjawab "kenapa tiap toko beda seat-nya":
            // batasnya berangkat dari jatah paket, lalu naik sendiri tiap kali
            // tenant membayar penambahan pengguna. Tanpa keduanya berdampingan,
            // yang terlihat cuma angka yang seolah ditetapkan sembarangan.
            'plan_included_seats' => $subscription->plan?->included_seats,
            'plan_extra_seat_price' => $subscription->plan === null
                ? null
                : (float) $subscription->plan->extra_seat_price,
            'pricing_track' => $subscription->pricing_track,
            'track_changed_at' => $subscription->track_changed_at?->toDateString(),
            'track_reverts_at' => $subscription->track_reverts_at?->toDateString(),
            'seats' => $subscription->seats,
            'seats_used' => $subscription->activeSeatsUsed(),
            'seat_high_water' => $subscription->seat_high_water,
            'provisional_blocked' => $subscription->provisional_blocked,
            'price_locked' => $subscription->price_locked === null
                ? null
                : (float) $subscription->price_locked,
            'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
            'current_period_start' => $subscription->current_period_start?->toDateString(),
            'current_period_end' => $subscription->current_period_end?->toDateString(),
        ];
    }

    /**
     * Paket yang bisa dihuni tenant, beserta apa saja yang berubah bila ia
     * dipindahkan ke sana.
     *
     * Ketiga angkanya ikut — tarif, jatah pengguna, kuota AI — karena satu
     * perpindahan menggerakkan ketiganya sekaligus. Daftar berisi nama paket
     * saja akan membuat pemilik SaaS memindahkan tenant sambil menebak
     * akibatnya.
     *
     * `ai_daily_limit` dikirim `null` bila paketnya tidak menyetel batas
     * sendiri. Menyelesaikannya jadi angka bawaan di sini akan membuat "10 karena
     * paket ini" tak bisa dibedakan dari "10 karena kebetulan itu bawaan hari
     * ini" — bedaan yang sama yang dijaga halaman Aturan Harga.
     *
     * @return array<int, array<string, mixed>>
     */
    private function planCatalog(): array
    {
        return Plan::query()
            ->where('is_active', true)
            ->orderBy('base_price')
            ->orderBy('name')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'base_price' => (float) $plan->base_price,
                'included_seats' => $plan->included_seats,
                'extra_seat_price' => (float) $plan->extra_seat_price,
                'ai_daily_limit' => $plan->limit(Plan::LIMIT_AI_DAILY),
                'is_adaptive_fallback' => $plan->is_adaptive_fallback,
            ])
            ->all();
    }

    /**
     * Seluruh riwayat tagihan tenant, terbaru di atas.
     *
     * Tidak dipotong 12 seperti halaman tenant: yang membuka halaman ini sedang
     * menjawab pertanyaan tentang satu akun, dan "tagihan bulan apa yang tidak
     * pernah dibayar" adalah pertanyaan yang jawabannya bisa berumur setahun.
     */
    private function invoicePayload(Tenant $tenant): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        return InvoiceResource::collection(
            $tenant->invoices()
                ->with('verifier:id,name')
                ->orderByDesc('period')
                ->orderByDesc('id')
                ->get()
        );
    }
}

<?php

namespace App\Services\Pricing;

use App\Models\Plan;
use App\Services\PricingService;
use App\Services\SubscriptionService;
use Carbon\Carbon;

/**
 * Harga sebagaimana boleh dibacakan kepada orang yang belum punya sesi.
 *
 * Berdiri karena tiga entri backlog menuntut angka yang sama di tempat berbeda
 * — `[BL-041]`(c) untuk halaman `/harga`, `[BL-066]`(c) untuk penjelasan cara
 * tarif dihitung di landing, dan `[BL-067]`(a) untuk isi paket — dan ketiganya
 * menulis syarat yang sama persis: angkanya ditarik dari `plans` dan
 * `pricing_rules`, bukan diketik di HTML. Satu pembaca untuk ketiganya, supaya
 * tidak lahir tabel ketiga yang bisa basi sendiri.
 *
 * Kelas ini TIDAK menyentuh tenant, sesi, maupun `transactions`. Itu bukan
 * kebetulan melainkan syarat: ia dipanggil dari halaman tanpa sesi, dan segala
 * yang butuh tenant — bracket berjalan, perkiraan, kelayakan — tinggal di
 * `PricingService` dan `AdaptiveEligibility` yang memang punya tenantnya.
 */
class PublicPricing
{
    public function __construct(private readonly PricingService $pricing) {}

    /**
     * Seluruh yang perlu diketahui halaman publik tentang harga.
     *
     * @return array{
     *     plans: list<array{slug: string, name: string, price: float, included_seats: int, extra_seat_price: float, ai_daily: int|null, is_free: bool, hosts_adaptive: bool, is_post_trial_target: bool}>,
     *     adaptive: array{ladder: list<array{label: string, price: float, min: float|null, max: float|null}>, ceiling: float|null, host_plan: string|null},
     *     trial_months: int,
     *     track_switch_minimum_months: int
     * }
     */
    public function snapshot(?Carbon $asOf = null): array
    {
        $plans = $this->plans();

        return [
            'plans' => $plans,
            'adaptive' => [
                // Tangganya apa adanya dari `pricing_rules`; tidak diturunkan
                // ulang di sini. Halaman publik dan `/langganan/harga-adaptif`
                // membaca fungsi yang sama, jadi keduanya tidak bisa berbeda.
                'ladder' => $this->pricing->adaptiveLadder($asOf),
                // `null` berarti tangganya tidak berujung — BUKAN ambang nol.
                // Halaman yang menukar keduanya akan memberi tahu setiap
                // pengunjung bahwa ia tidak berhak atas Harga Adaptif, persis
                // arah gagal yang diperingatkan `[BL-048]`.
                'ceiling' => $this->pricing->adaptiveCeiling($asOf),
                'host_plan' => collect($plans)->firstWhere('hosts_adaptive')['name'] ?? null,
            ],
            'trial_months' => SubscriptionService::trialMonths(),
            'track_switch_minimum_months' => SubscriptionService::trackSwitchMinimumMonths(),
        ];
    }

    /**
     * Yang harus diketahui orang SEBELUM ia menekan "Daftar": berapa lama masa
     * gratisnya, ke paket mana akunnya berpindah sesudahnya, dan berapa
     * tarifnya (`[BL-071]`).
     *
     * `post_trial_plan` bernilai `null` untuk dua keadaan yang berakibat sama —
     * pemilik SaaS belum menunjuk paket tujuan, atau yang ditunjuk justru paket
     * gratis itu sendiri. Keduanya persis yang ditolak
     * `SubscriptionService::graduateExpiredTrials()`, jadi tidak akan ada
     * perpindahan yang bisa diumumkan. Halaman daftar harus diam soal paket
     * tujuan dalam keadaan itu; yang TIDAK boleh ia lakukan adalah menyimpulkan
     * darinya bahwa masa gratisnya tak berujung.
     *
     * @return array{months: int, invoice_lead_days: int, post_trial_plan: array{name: string, price: float}|null}
     */
    public function trialNotice(): array
    {
        $target = Plan::postTrialTarget();
        $pindah = $target !== null && $target->slug !== Plan::SLUG_DEFAULT;

        return [
            'months' => SubscriptionService::trialMonths(),
            // Disebut karena inilah yang membuat tagihan pertama tiba sebagai
            // pemberitahuan, bukan kejutan — dan angkanya kebijakan yang bisa
            // berubah, sama seperti panjang masa gratisnya.
            'invoice_lead_days' => (int) config('subscription.invoice_lead_days'),
            'post_trial_plan' => $pindah ? [
                'name' => $target->name,
                // Angka mentah; pemformatan Rupiah milik lapisan tampilan.
                'price' => (float) $target->base_price,
            ] : null,
        ];
    }

    /**
     * Paket yang benar-benar dijual, termurah lebih dulu.
     *
     * Hanya `is_active`. Paket nonaktif adalah paket yang pemiliknya berhenti
     * menjual, dan memajangnya di halaman publik mengundang pendaftaran ke
     * sesuatu yang tidak ada tempatnya.
     *
     * @return list<array{slug: string, name: string, price: float, included_seats: int, extra_seat_price: float, ai_daily: int|null, is_free: bool, hosts_adaptive: bool, is_post_trial_target: bool}>
     */
    private function plans(): array
    {
        return Plan::query()
            ->where('is_active', true)
            // `id` sebagai pemutus supaya urutannya tetap sama antar permintaan
            // saat dua paket berharga sama — tabel harga yang barisnya
            // berpindah-pindah antar muat terbaca sebagai halaman yang rusak.
            ->orderBy('base_price')
            ->orderBy('id')
            ->get()
            ->map(fn (Plan $plan) => [
                'slug' => $plan->slug,
                'name' => $plan->name,
                // Angka mentah; pemformatan Rupiah milik lapisan tampilan.
                'price' => (float) $plan->base_price,
                'included_seats' => (int) $plan->included_seats,
                'extra_seat_price' => (float) $plan->extra_seat_price,
                // `null` berarti paket ini tidak menyetel batasnya sendiri dan
                // karenanya ikut bawaan platform — bukan berarti nol. Bedanya
                // dijaga `Plan::limit()`, dan halaman publik harus menuliskannya
                // sebagai "ikut bawaan", bukan memajang angka yang ditebak.
                'ai_daily' => $plan->limit(Plan::LIMIT_AI_DAILY),
                'is_free' => (float) $plan->base_price === 0.0,
                'hosts_adaptive' => $plan->is_adaptive_fallback,
                'is_post_trial_target' => $plan->is_post_trial_target,
            ])
            ->all();
    }
}

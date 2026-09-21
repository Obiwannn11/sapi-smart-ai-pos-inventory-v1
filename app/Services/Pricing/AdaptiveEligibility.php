<?php

namespace App\Services\Pricing;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\PricingService;

/**
 * Satu pertanyaan, dijawab satu kali: apakah omzet tenant ini sudah melampaui
 * ujung tangga Harga Adaptif?
 *
 * Dipisah dari `SubscriptionService` karena dua jalur yang berlawanan arah
 * menanyakannya, dan keduanya harus mendapat jawaban yang sama:
 *
 *   - **pintu masuk** — tenant jalur tetap yang hendak mengajukan keringanan.
 *     Omzetnya belum pernah dihitung sistem (gerbang privasi), jadi angkanya
 *     datang dari `SubsidyEstimator`: dihitung untuk mata pemiliknya sendiri,
 *     dalam permintaan itu juga, dan tidak pernah disimpan.
 *   - **pintu keluar** — tenant Adaptif yang tumbuh melewati ambang. Ia sudah
 *     menyetujui pembukaan omzetnya, jadi angkanya diambil dari konteks harga
 *     yang sesungguhnya (`tenant_monthly_metrics`), bukan dari perkiraan.
 *
 * Yang TIDAK dilakukan kelas ini, dan itu inti `[BL-048]`: menyimpulkan "omzet
 * terlalu tinggi" dari harga yang kosong. `resolveFor()` mengembalikan `price =
 * null` karena tiga sebab berbeda — omzet di atas bracket teratas, lubang di
 * tabel bracket, dan tenant yang memang tak punya data omzet — dan
 * menyamaratakan ketiganya berarti satu baris syarat yang salah ketik diam-diam
 * mendorong seluruh tenant keluar dari keringanan. Yang dibandingkan di sini
 * adalah ANGKANYA terhadap ambang, dan hanya bila keduanya benar-benar ada.
 */
class AdaptiveEligibility
{
    /** Boleh mengajukan sekarang. */
    public const REASON_ELIGIBLE = 'eligible';

    /** Sudah di jalur Adaptif — tak ada yang perlu diajukan. */
    public const REASON_ACTIVE = 'active';

    /** Masih dalam jarak minimum dari perpindahan jalur terakhir. */
    public const REASON_COOLDOWN = 'cooldown';

    /** Omzetnya di atas ujung tangga Adaptif; jalurnya paket berbayar penuh. */
    public const REASON_ABOVE_CEILING = 'above_ceiling';

    public function __construct(
        private readonly PricingService $pricing,
        private readonly SubsidyEstimator $estimator,
    ) {}

    /**
     * Omzet bulan tutup terakhir milik tenant, dari sumber yang boleh dipakai
     * untuknya, atau `null` bila belum ada angkanya sama sekali.
     *
     * Tenant Adaptif dibaca dari konteks harga sungguhan; tenant jalur tetap
     * dari perkiraan yang tak pernah disimpan. Keduanya menjawab pertanyaan
     * yang sama dan tak satu pun melanggar apa yang dijanjikan dokumen
     * consent-nya.
     */
    public function measuredRevenueFor(Tenant $tenant): ?float
    {
        if ($tenant->pricing_track === Subscription::TRACK_SUBSIDIZED) {
            $revenue = $this->pricing->resolveFor($tenant)['context'][PricingService::DIMENSION_REVENUE] ?? null;

            return $revenue === null ? null : (float) $revenue;
        }

        $estimate = $this->estimator->estimateFor($tenant, 0.0);

        // Nol karena tak ada penjualan tercatat BUKAN omzet yang terukur.
        // Membedakannya di sini hanya berpengaruh pada apa yang dikatakan ke
        // layar — nol tetap di bawah ambang mana pun — tapi "belum terukur"
        // dan "terukur nol" adalah dua kalimat yang berbeda.
        return $estimate['transaction_count'] === 0 ? null : (float) $estimate['revenue'];
    }

    /**
     * Ambang keluar Harga Adaptif, atau `null` bila tangganya tidak berujung.
     */
    public function ceiling(): ?float
    {
        return $this->pricing->adaptiveCeiling();
    }

    /**
     * Apakah omzet tenant sudah di atas ambang — dan karenanya jalur satu-satunya
     * baginya adalah paket berbayar penuh.
     *
     * Menutup ke arah yang aman: tanpa ambang, atau tanpa angka omzet, jawabannya
     * "tidak". Menolak orang butuh bukti; membiarkannya tidak.
     */
    public function isAboveCeiling(Tenant $tenant): bool
    {
        $ceiling = $this->ceiling();
        $revenue = $this->measuredRevenueFor($tenant);

        return $ceiling !== null && $revenue !== null && $revenue >= $ceiling;
    }
}

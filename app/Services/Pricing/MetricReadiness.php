<?php

namespace App\Services\Pricing;

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMonthlyMetric;
use Illuminate\Support\Carbon;

/**
 * Apakah tarif tenant untuk sebuah periode sudah BISA ditetapkan.
 *
 * Berdiri sendiri, di depan `PricingService`, dan itu bukan pemisahan yang
 * kosmetik. `resolveFor()` tidak pernah kehabisan jawaban: bila tak ada aturan
 * yang cocok ia menjatuhkan tenant ke paket penampung, dan angka itu **berupa
 * harga yang sah**. Bagi tenant Adaptif yang ringkasan omzetnya belum ada,
 * jawaban itu justru bencana — ia ditagih tarif penampung, yang lazimnya paket
 * termahal, karena sebuah baris yang baru akan ditulis besok pagi.
 *
 * Karena itu pertanyaan "sudah bisa dihitung belum" harus dijawab SEBELUM
 * penetapan harga dimulai, bukan disimpulkan dari hasilnya. Inilah yang membuat
 * `[BL-080]` opsi (i) — tunda penerbitan sampai ringkasannya ada — bisa
 * dibedakan dari "tidak ada bracket yang cocok", dua keadaan yang menghasilkan
 * `source` sama tapi menuntut tindakan yang berlawanan.
 */
class MetricReadiness
{
    /**
     * Apakah tenant ini tarifnya bergantung pada ringkasan omzet.
     *
     * Hanya jalur Adaptif. Jalur Harga Tetap tidak punya baris ringkasan sama
     * sekali — gerbang privasi di `ComputeTenantMonthlyRevenue` memastikan
     * datanya memang tidak pernah ada, bukan disembunyikan — jadi menundanya
     * berarti mencabut masa siap tenant yang tidak menukar apa pun.
     */
    public function dependsOnMetric(Tenant $tenant): bool
    {
        return $tenant->pricing_track === Subscription::TRACK_SUBSIDIZED;
    }

    /**
     * Apakah periode yang dibuka pada `$asOf` sudah bisa ditetapkan harganya.
     *
     * Bulan yang ditanyakan sengaja diambil dari `MonthlyMetricResolver`, bukan
     * dihitung ulang di sini: penjaga yang memakai definisi bulannya sendiri
     * akan meloloskan periode yang kemudian ternyata tak bisa dihargai, dan
     * selisih sehalus itu hanya akan terlihat pada tagihan tenant.
     */
    public function isReadyFor(Tenant $tenant, Carbon $asOf): bool
    {
        if (! $this->dependsOnMetric($tenant)) {
            return true;
        }

        return TenantMonthlyMetric::where('tenant_id', $tenant->id)
            ->where('period', MonthlyMetricResolver::requiredPeriodFor($asOf))
            ->exists();
    }
}

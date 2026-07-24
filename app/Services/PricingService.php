<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantMonthlyMetric;

/**
 * Penetapan harga jalur subsidi: omset bulanan → bracket → tarif.
 *
 * Bracket-nya masih dibaca dari `config/subscription.php`. Tahap D
 * memindahkannya ke tabel `pricing_rules` supaya pemilik SaaS bisa mengubahnya
 * sendiri; bentuk pengembalian method di sini sengaja dibuat sama dengan bentuk
 * baris tabel itu agar perpindahannya tidak menyentuh pemanggilnya.
 */
class PricingService
{
    /**
     * Bracket yang memuat angka omset ini.
     *
     * @return array{label: string, min: int, max: int|null, price: int}|null
     */
    public function bracketFor(float $revenue): ?array
    {
        foreach (config('subscription.revenue_brackets') as $bracket) {
            $dibawahBatasAtas = $bracket['max'] === null || $revenue < $bracket['max'];

            if ($revenue >= $bracket['min'] && $dibawahBatasAtas) {
                return $bracket;
            }
        }

        return null;
    }

    /**
     * Ringkasan omset terakhir milik tenant, bila ada.
     *
     * Membaca HANYA dari tabel ringkasan — tidak pernah dari `transactions`.
     */
    public function latestMetricFor(Tenant $tenant): ?TenantMonthlyMetric
    {
        return TenantMonthlyMetric::where('tenant_id', $tenant->id)
            ->orderByDesc('period')
            ->first();
    }

    /**
     * Bracket berjalan tenant beserta angka omset yang mendasarinya.
     *
     * Angka persisnya sengaja dipisahkan dari labelnya: pemanggil yang hanya
     * butuh menampilkan daftar cukup memakai `label`, dan `revenue` hanya
     * disentuh di jalur yang memang tercatat di audit log.
     *
     * @return array{period: string, label: string|null, price: int|null, revenue: float}|null
     */
    public function currentBracketFor(Tenant $tenant): ?array
    {
        $metric = $this->latestMetricFor($tenant);

        if ($metric === null) {
            return null;
        }

        $bracket = $this->bracketFor((float) $metric->revenue);

        return [
            'period' => $metric->period,
            'label' => $bracket['label'] ?? null,
            'price' => $bracket['price'] ?? null,
            'revenue' => (float) $metric->revenue,
        ];
    }
}

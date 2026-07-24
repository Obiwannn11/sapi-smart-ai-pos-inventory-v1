<?php

namespace App\Services;

use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\TenantMonthlyMetric;
use Illuminate\Support\Carbon;

/**
 * Penetapan harga jalur subsidi: omzet bulanan → bracket → tarif.
 *
 * Aturannya dibaca dari tabel `pricing_rules`, yang di-CRUD pemilik SaaS dari
 * platform console. Tidak ada satu pun angka tarif yang hidup di kode.
 */
class PricingService
{
    /**
     * Bracket yang memuat angka omzet ini, menurut aturan yang berlaku pada
     * tanggal tertentu.
     *
     * `$asOf` ada demi grandfathering: menghitung ulang periode lama harus
     * memakai aturan yang berlaku SAAT ITU, bukan aturan hari ini. Tanpa
     * parameter ini, satu kali edit tarif akan diam-diam menulis ulang sejarah
     * penetapan harga seluruh klien.
     *
     * @return array{label: string, min: float, max: float|null, price: float}|null
     */
    public function bracketFor(float $revenue, ?Carbon $asOf = null): ?array
    {
        $rule = PricingRule::query()
            ->effectiveOn($asOf)
            ->orderByDesc('effective_from')
            ->orderBy('min_revenue')
            ->get()
            // Beberapa aturan bisa menutupi rentang yang sama dengan tanggal
            // berlaku berbeda; yang menang adalah yang tanggalnya paling baru
            // namun sudah lewat — karena itu urutannya menurun di atas.
            ->unique(fn (PricingRule $rule) => $rule->label)
            ->first(fn (PricingRule $rule) => $rule->covers($revenue));

        if ($rule === null) {
            return null;
        }

        return [
            'label' => $rule->label,
            'min' => (float) $rule->min_revenue,
            'max' => $rule->max_revenue === null ? null : (float) $rule->max_revenue,
            'price' => (float) $rule->price,
        ];
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
     * @return array{period: string, label: string|null, price: float|null, revenue: float}|null
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

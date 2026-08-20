<?php

namespace App\Services\Pricing;

use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Omzet bulanan tenant, dari tabel ringkasan — tidak pernah dari `transactions`.
 *
 * Batas itu bukan gaya penulisan: `tenant_monthly_metrics` adalah satu-satunya
 * jalan data penjualan tenant sampai ke penetapan harga, dan itulah yang
 * membuat `PlatformArchTest` bisa menegakkannya.
 *
 * Pencarian ringkasannya sendiri ada di `MonthlyMetricResolver`, bersama alasan
 * kenapa bulan yang diminta diperlakukan tegas.
 */
class MonthlyRevenueResolver extends MonthlyMetricResolver
{
    public function resolve(Tenant $tenant, ?Carbon $asOf = null): float|string|null
    {
        $metric = $this->metricFor($tenant, $asOf);

        return $metric === null ? null : (float) $metric->revenue;
    }
}

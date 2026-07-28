<?php

namespace App\Services\Pricing;

use App\Models\Tenant;
use App\Models\TenantMonthlyMetric;
use Illuminate\Support\Carbon;

/**
 * Omzet bulanan tenant, dari tabel ringkasan — tidak pernah dari `transactions`.
 *
 * Batas itu bukan gaya penulisan: `tenant_monthly_metrics` adalah satu-satunya
 * jalan data penjualan tenant sampai ke penetapan harga, dan itulah yang
 * membuat `PlatformArchTest` bisa menegakkannya.
 */
class MonthlyRevenueResolver implements DimensionResolver
{
    public function resolve(Tenant $tenant, ?Carbon $asOf = null): float|string|null
    {
        $metric = $this->metricFor($tenant, $asOf);

        return $metric === null ? null : (float) $metric->revenue;
    }

    /**
     * Ringkasan yang berlaku pada tanggal tertentu.
     *
     * Tanpa `$asOf` diambil yang terbaru. Dengan `$asOf`, diambil ringkasan
     * terbaru yang periodenya TIDAK melewati bulan itu — menghitung ulang
     * tagihan Maret harus memakai omzet yang sudah diketahui pada Maret, bukan
     * omzet Juli yang baru terkumpul empat bulan kemudian.
     */
    protected function metricFor(Tenant $tenant, ?Carbon $asOf): ?TenantMonthlyMetric
    {
        return TenantMonthlyMetric::where('tenant_id', $tenant->id)
            ->when($asOf !== null, fn ($query) => $query->where('period', '<=', $asOf->format('Y-m')))
            ->orderByDesc('period')
            ->first();
    }
}

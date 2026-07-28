<?php

namespace App\Services\Pricing;

use App\Models\Tenant;
use App\Models\TenantMonthlyMetric;
use Illuminate\Support\Carbon;

/**
 * Cacah transaksi bulanan tenant.
 *
 * Sumbernya tabel ringkasan yang sama dengan omzet, dan kolomnya sudah terisi
 * sejak job penghitung omzet berdiri — jadi dimensi ini tidak menambah satu pun
 * pengambilan data baru. Ia berguna sebagai ukuran pemakaian yang tidak
 * bergantung harga jual: dua warung beromzet sama bisa sangat berbeda beban
 * pemakaiannya bila yang satu menjual banyak barang murah.
 */
class TransactionCountResolver implements DimensionResolver
{
    public function resolve(Tenant $tenant, ?Carbon $asOf = null): float|string|null
    {
        $metric = TenantMonthlyMetric::where('tenant_id', $tenant->id)
            ->when($asOf !== null, fn ($query) => $query->where('period', '<=', $asOf->format('Y-m')))
            ->orderByDesc('period')
            ->first();

        return $metric === null ? null : (float) $metric->transaction_count;
    }
}

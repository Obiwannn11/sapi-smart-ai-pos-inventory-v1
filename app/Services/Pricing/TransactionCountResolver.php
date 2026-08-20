<?php

namespace App\Services\Pricing;

use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Cacah transaksi bulanan tenant.
 *
 * Sumbernya tabel ringkasan yang sama dengan omzet, dan kolomnya sudah terisi
 * sejak job penghitung omzet berdiri — jadi dimensi ini tidak menambah satu pun
 * pengambilan data baru. Ia berguna sebagai ukuran pemakaian yang tidak
 * bergantung harga jual: dua warung beromzet sama bisa sangat berbeda beban
 * pemakaiannya bila yang satu menjual banyak barang murah.
 *
 * Membaca lewat `MonthlyMetricResolver` supaya ia terikat pada bulan yang sama
 * dengan dimensi omzet. Dua dimensi dari satu baris ringkasan yang memungut
 * bulan berbeda akan melahirkan aturan harga yang syaratnya tak pernah bisa
 * dipenuhi bersamaan.
 */
class TransactionCountResolver extends MonthlyMetricResolver
{
    public function resolve(Tenant $tenant, ?Carbon $asOf = null): float|string|null
    {
        $metric = $this->metricFor($tenant, $asOf);

        return $metric === null ? null : (float) $metric->transaction_count;
    }
}

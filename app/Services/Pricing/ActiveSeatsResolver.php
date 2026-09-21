<?php

namespace App\Services\Pricing;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Jumlah pengguna aktif tenant — dimensi yang berlaku di kedua jalur harga.
 *
 * Dipakai `seat_high_water` bila ada, bukan cacah aktif hari ini. Alasannya
 * sama dengan alasan kolom itu dibuat: menonaktifkan staf sehari sebelum
 * tanggal tagih tidak boleh menurunkan harga sebulan penuh. Tenant tanpa
 * langganan jatuh kembali ke cacah langsung.
 */
class ActiveSeatsResolver implements DimensionResolver
{
    public function resolve(Tenant $tenant, ?Carbon $asOf = null): float|string|null
    {
        $subscription = $tenant->subscription()->first();

        if ($subscription === null) {
            return (float) $tenant->users()->where('is_active', true)->count();
        }

        return (float) max($subscription->seat_high_water, $this->activeNow($subscription));
    }

    protected function activeNow(Subscription $subscription): int
    {
        return $subscription->activeSeatsUsed();
    }
}

<?php

namespace App\Services\Queue;

use App\Models\Transaction;
use Illuminate\Support\Carbon;

/**
 * Strategi bawaan: urutan berjalan yang reset tiap hari penjualan, per tenant.
 *
 * Batas yang disadari: `whereEffectiveDate()` memakai `whereRaw` atas
 * `COALESCE(...)`, sehingga `lockForUpdate` di sini tidak bisa bersandar pada
 * indeks dengan rapi. Untuk satu kasir itu tidak terasa. Untuk ledakan
 * self-order QR bersamaan, dua pesanan bisa memperoleh nomor sama — dan inilah
 * alasan kedua kenapa nomor antrian tidak boleh jadi identitas: tabrakan label
 * merepotkan, tabrakan identitas merusak.
 *
 * Skema yang lebih kuat (baris penghitung per tenant per hari) masuk di balik
 * antarmuka yang sama bila terbukti perlu.
 */
class DailySequenceAllocator implements QueueNumberAllocator
{
    public function allocate(int $tenantId, Carbon $occurredAt): int
    {
        $last = Transaction::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('queue_number')
            ->whereEffectiveDate($occurredAt)
            ->lockForUpdate()
            ->max('queue_number');

        return (int) $last + 1;
    }
}

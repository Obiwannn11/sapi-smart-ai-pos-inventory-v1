<?php

namespace App\Services\Queue;

use Illuminate\Support\Carbon;

/**
 * Pengalokasi nomor antrian.
 *
 * Sengaja sebuah seam, bukan method privat di TransactionService. Fase offline
 * akan menukar strateginya — blok nomor per perangkat, atau awalan per
 * perangkat seperti `A-12`/`B-12` — dan penukaran itu harus jadi penggantian
 * satu kelas, bukan bedah ulang `checkout()`.
 */
interface QueueNumberAllocator
{
    /** Nomor antrian untuk satu tenant pada hari penjualan tertentu. */
    public function allocate(int $tenantId, Carbon $occurredAt): int;
}

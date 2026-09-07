<?php

namespace App\Console\Commands;

use App\Services\BusinessClock;
use App\Services\ExpiredStockRecorder;
use Illuminate\Console\Command;

/**
 * Stempel harian barang yang basi ([BL-105] butir 2).
 *
 * Ia tidak mengubah apa pun — tidak stok, tidak status varian, tidak satu pun
 * layar. Ia menulis satu baris untuk tiap varian yang melewati kedaluwarsanya
 * dengan stok tersisa, dan itulah seluruh gunanya: angka yang tidak dicatat
 * pada harinya tidak akan pernah bisa dihitung lagi belakangan.
 */
class RecordExpiredStock extends Command
{
    protected $signature = 'stock:record-expired
        {--date= : Hari toko sebagai acuan (YYYY-MM-DD); untuk menjalankan ulang hari yang terlewat}
        {--dry-run : Tampilkan jumlah yang akan dicatat tanpa mencatat}';

    protected $description = 'Catat barang yang melewati tanggal kedaluwarsanya dengan stok tersisa';

    public function handle(ExpiredStockRecorder $recorder): int
    {
        $date = $this->option('date') ?: BusinessClock::today();
        $dryRun = (bool) $this->option('dry-run');

        $written = $recorder->record($date, dryRun: $dryRun);

        $this->info(sprintf(
            'Barang yang basi sebelum %s: %d varian%s.',
            $date,
            $written,
            $dryRun ? ' akan dicatat (dry-run)' : ' dicatat',
        ));

        return self::SUCCESS;
    }
}

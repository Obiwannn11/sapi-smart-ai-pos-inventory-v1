<?php

namespace App\Console\Commands;

use App\Jobs\ComputeTenantMonthlyRevenue;
use App\Models\TenantMonthlyMetric;
use Illuminate\Console\Command;

/**
 * Menjalankan penghitung omset bulanan untuk tenant jalur subsidi.
 *
 * Dijalankan awal tiap bulan atas bulan yang baru saja tutup. `--period` ada
 * untuk menghitung ulang bulan tertentu bila sebuah transaksi offline baru
 * tersinkron belakangan.
 */
class ComputeTenantRevenue extends Command
{
    protected $signature = 'subscriptions:compute-revenue {--period= : Periode YYYY-MM yang dihitung (default: bulan lalu)}';

    protected $description = 'Hitung omset bulanan tenant jalur subsidi ke tabel ringkasan';

    public function handle(): int
    {
        $period = $this->option('period');

        if ($period !== null && ! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('Format --period harus YYYY-MM.');

            return self::FAILURE;
        }

        ComputeTenantMonthlyRevenue::dispatchSync($period);

        $target = $period ?? now()->subMonth()->format('Y-m');
        $count = TenantMonthlyMetric::where('period', $target)->count();

        $this->info("Periode {$target}: {$count} tenant jalur subsidi terhitung.");

        return self::SUCCESS;
    }
}

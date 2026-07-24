<?php

namespace App\Console\Commands;

use App\Models\TenantMonthlyMetric;
use Illuminate\Console\Command;

/**
 * Pemangkasan ringkasan omset yang melewati masa retensi.
 *
 * Data omset adalah data paling sensitif yang dipegang panel ini. Menyimpannya
 * lebih lama dari yang dibutuhkan bukan kehati-hatian, melainkan tanggungan
 * tambahan: tiap baris yang tersisa adalah sesuatu yang harus dijaga tanpa ada
 * lagi yang membacanya.
 */
class PruneTenantMetrics extends Command
{
    protected $signature = 'subscriptions:prune-metrics {--dry-run : Tampilkan jumlah yang akan dihapus tanpa menghapus}';

    protected $description = 'Buang ringkasan omset tenant yang melewati masa retensinya';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $months = (int) config('subscription.metrics_retention_months');
        $cutoff = now()->subMonths($months)->format('Y-m');

        // Perbandingan string pada format YYYY-MM aman secara leksikografis —
        // '2024-09' < '2026-07' — jadi tidak perlu mengurai tanggalnya.
        $query = TenantMonthlyMetric::where('period', '<', $cutoff);
        $count = $query->count();

        if ($count > 0 && ! $dryRun) {
            $query->delete();
        }

        $this->info(sprintf(
            'Retensi %d bulan (sebelum %s): %d baris%s.',
            $months,
            $cutoff,
            $count,
            $dryRun ? ' akan dihapus (dry-run)' : ' dihapus'
        ));

        return self::SUCCESS;
    }
}

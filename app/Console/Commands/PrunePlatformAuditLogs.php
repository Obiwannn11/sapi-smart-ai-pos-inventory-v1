<?php

namespace App\Console\Commands;

use App\Models\PlatformAuditLog;
use Illuminate\Console\Command;

/**
 * Pemangkasan jejak audit platform sesuai retensi per derajat.
 *
 * Rutin dibuang lebih cepat karena nilainya cepat luruh; sensitif disimpan
 * jauh lebih lama justru karena itulah yang dipakai membuktikan. Angkanya di
 * config/platform-audit.php.
 */
class PrunePlatformAuditLogs extends Command
{
    protected $signature = 'platform:prune-audit-logs {--dry-run : Tampilkan jumlah yang akan dihapus tanpa menghapus}';

    protected $description = 'Buang jejak audit platform yang melewati masa retensinya';

    public function handle(): int
    {
        $retention = config('platform-audit.retention_days');
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        foreach ($retention as $severity => $days) {
            $cutoff = now()->subDays((int) $days);

            $query = PlatformAuditLog::where('severity', $severity)
                ->where('created_at', '<', $cutoff);

            $count = $query->count();
            $total += $count;

            if ($count > 0 && ! $dryRun) {
                $query->delete();
            }

            $this->line(sprintf(
                '%-10s retensi %4d hari (sebelum %s): %d baris%s',
                $severity,
                $days,
                $cutoff->toDateString(),
                $count,
                $dryRun ? ' (dry-run)' : ''
            ));
        }

        $this->info($dryRun
            ? "Total {$total} baris akan dihapus."
            : "Total {$total} baris dihapus.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\CashDrawer;
use App\Services\CashDrawerExpiryService;
use Illuminate\Console\Command;

/**
 * Sesi kas yang lewat umurnya ditutup paksa ([BL-088]).
 *
 * Sesi yang ditutup di sini tidak mengaku sudah dihitung: `closing_amount` dan
 * `difference` tetap `null`. Alasannya ada di CashDrawerExpiryService.
 */
class ExpireCashDrawers extends Command
{
    protected $signature = 'cash-drawers:expire
        {--dry-run : Tampilkan jumlah yang akan ditutup tanpa menutupnya}';

    protected $description = 'Tutup paksa sesi kas yang lewat umurnya';

    public function handle(CashDrawerExpiryService $expiry): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $closed = $expiry->expire(dryRun: $dryRun);

        $this->info(sprintf(
            'Sesi kas dibuka sebelum %s (%d jam): %d%s.',
            CashDrawer::staleCutoff()->toDateTimeString(),
            CashDrawer::MAX_SESSION_HOURS,
            $closed,
            $dryRun ? ' akan ditutup sistem (dry-run)' : ' ditutup sistem',
        ));

        return self::SUCCESS;
    }
}

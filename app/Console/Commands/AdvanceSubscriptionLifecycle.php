<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * Memindahkan tenant ke keadaan berikutnya begitu tenggatnya lewat.
 *
 * Dijalankan harian. Tanpa ini, masa coba dan periode berbayar akan berakhir
 * di atas kertas saja: kolom tanggalnya lewat, tapi tenant tetap berstatus
 * `trial` selamanya dan tidak satu pun batas berlaku.
 */
class AdvanceSubscriptionLifecycle extends Command
{
    protected $signature = 'subscriptions:advance-lifecycle {--dry-run : Tampilkan jumlah yang akan berpindah tanpa mengubah apa pun}';

    protected $description = 'Pindahkan tenant yang periodenya lewat ke masa tenggang, dan yang tenggangnya habis ke penangguhan';

    public function handle(SubscriptionService $subscriptions): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $subscriptions->advanceLifecycle($dryRun);

        $this->line(sprintf(
            'Periode lewat  → masa tenggang : %d tenant%s',
            $result['expired'],
            $dryRun ? ' (dry-run)' : ''
        ));

        $this->line(sprintf(
            'Tenggang habis → penangguhan   : %d tenant%s',
            $result['suspended'],
            $dryRun ? ' (dry-run)' : ''
        ));

        $this->info($dryRun
            ? 'Tidak ada yang diubah.'
            : 'Selesai.');

        return self::SUCCESS;
    }
}

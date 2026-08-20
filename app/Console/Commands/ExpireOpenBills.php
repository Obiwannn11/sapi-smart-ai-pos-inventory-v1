<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\OpenBillExpiryService;
use Illuminate\Console\Command;

/**
 * Tagihan terbuka yang lewat 24 jam dipindahkan ke kas negatif ([BL-031]).
 *
 * Sebelum ini tidak ada satu pun tugas terjadwal yang menyentuh transaksi
 * `pending`: sebuah tagihan yang ditinggalkan hidup selamanya, menyandera stok
 * yang sudah berkurang sejak tagihan dibuat, tanpa ada yang pernah menagihnya
 * kembali. Itu bukan keputusan — itu bawaan yang tak pernah dipilih.
 *
 * Ia TIDAK memulihkan stok. Alasannya ada di OpenBillExpiryService.
 */
class ExpireOpenBills extends Command
{
    protected $signature = 'open-bills:expire
        {--dry-run : Tampilkan jumlah yang akan dipindahkan tanpa memindahkan}';

    protected $description = 'Pindahkan tagihan terbuka yang lewat umurnya ke kas negatif';

    public function handle(OpenBillExpiryService $expiry): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $moved = $expiry->expire(dryRun: $dryRun);

        $this->info(sprintf(
            'Tagihan terbuka lebih tua dari %s (%d jam): %d%s.',
            Transaction::openBillCutoff()->toDateTimeString(),
            Transaction::OPEN_BILL_LIFETIME_HOURS,
            $moved,
            $dryRun ? ' akan jadi kas negatif (dry-run)' : ' dipindahkan ke kas negatif',
        ));

        return self::SUCCESS;
    }
}

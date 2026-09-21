<?php

namespace App\Console\Commands;

use App\Services\PaymentProofService;
use Illuminate\Console\Command;

/**
 * Pembersihan foto bukti bayar yang tidak pernah melekat pada pembayaran.
 *
 * Fotonya diunggah SEBELUM penjualannya disimpan (lihat PaymentProofService),
 * jadi kasir yang memotret lalu membatalkan modal meninggalkan berkas yang
 * tidak pernah akan diklaim siapa pun. Tanpa perintah ini, tiap batal-potret
 * adalah kebocoran disk permanen di sebuah alur yang dipakai puluhan kali
 * sehari.
 *
 * **Ia HANYA menyentuh `pending/`.** Foto yang sudah melekat pada sebuah
 * pembayaran tidak pernah ia lihat. Berapa lama foto yang sudah diklaim
 * disimpan adalah keputusan produk yang pemilik sengaja tunda ("tanpa batas
 * untuk sekarang", 2026-08-19) — ini kebersihan, bukan kebijakan retensi, dan
 * mencampur keduanya di satu perintah akan membuat perubahan kebijakan
 * berisiko menghapus yang bukan sampah.
 */
class PruneUnclaimedPaymentProofs extends Command
{
    protected $signature = 'payment-proofs:prune-unclaimed
        {--hours=24 : Umur minimum berkas tertunda sebelum dianggap terlantar}
        {--dry-run : Tampilkan jumlah yang akan dihapus tanpa menghapus}';

    protected $description = 'Buang foto bukti bayar tertunda yang tidak pernah diklaim sebuah pembayaran';

    public function handle(PaymentProofService $proofs): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // 24 jam, bukan satu jam. Batas yang ketat akan menghapus foto milik
        // modal pembayaran yang masih terbuka di perangkat kasir — dan yang
        // hilang bukan sekadar berkas, melainkan kemampuan menyelesaikan
        // penjualan yang sedang berlangsung.
        $cutoff = now()->subHours(max(1, (int) $this->option('hours')));

        $disk = $proofs->files()->disk();
        $deleted = 0;

        foreach ($disk->directories('payment-proofs') as $tenantDirectory) {
            $pending = "{$tenantDirectory}/pending";

            foreach ($disk->files($pending) as $file) {
                if ($disk->lastModified($file) >= $cutoff->getTimestamp()) {
                    continue;
                }

                $deleted++;

                if (! $dryRun) {
                    $disk->delete($file);
                }
            }
        }

        $this->info(sprintf(
            'Bukti bayar tertunda lebih tua dari %s: %d berkas%s.',
            $cutoff->toDateTimeString(),
            $deleted,
            $dryRun ? ' (dry-run, tidak dihapus)' : ' dihapus',
        ));

        return self::SUCCESS;
    }
}

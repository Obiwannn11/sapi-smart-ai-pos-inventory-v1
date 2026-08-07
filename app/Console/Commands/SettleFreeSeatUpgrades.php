<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\Billing\InvoiceSettlement;
use Illuminate\Console\Command;

/**
 * Tutup tagihan penambahan pengguna bernilai Rp 0 yang terlanjur menggantung.
 *
 * Sekali jalan, untuk tagihan yang terbit SEBELUM `[BL-049]` diperbaiki. Sejak
 * perbaikan itu, tagihan upgrade Rp 0 lunas seketika di `UpgradeController`
 * dan tidak pernah sampai ke keadaan ini lagi — jadi perintah ini semestinya
 * melaporkan nol pada pemanggilan kedua dan seterusnya.
 *
 * Sengaja sebuah perintah, bukan migrasi. Ia mengubah keadaan langganan
 * (seat tenant bertambah), dan perubahan semacam itu pantas dijalankan dengan
 * sadar dan bisa dilihat dulu hasilnya lewat `--dry-run` — bukan ikut terbawa
 * diam-diam oleh `migrate` pada saat rilis.
 */
class SettleFreeSeatUpgrades extends Command
{
    protected $signature = 'subscriptions:settle-free-upgrades {--dry-run : Tampilkan apa yang akan dilunasi tanpa mengubah apa pun}';

    protected $description = 'Lunasi tagihan penambahan pengguna bernilai Rp 0 yang masih menunggu bukti transfer';

    public function handle(InvoiceSettlement $settlement): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // `rejected` sengaja TIDAK ikut. Tagihan yang ditolak sudah pernah
        // dilihat manusia, dan melunasinya di sini berarti membatalkan
        // keputusan orang itu tanpa ia tahu. Kalau ada yang tersangkut di sana,
        // pemilik SaaS yang menyelesaikannya dari panel.
        $stuck = Invoice::query()
            ->where('kind', Invoice::KIND_UPGRADE)
            ->where('amount', '<=', 0)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_AWAITING_VERIFICATION])
            ->with('tenant')
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('Tidak ada tagihan penambahan pengguna Rp 0 yang menggantung.');

            return self::SUCCESS;
        }

        foreach ($stuck as $invoice) {
            $this->line(sprintf(
                '#%d · %s · seat %d → %d · jatuh tempo %s',
                $invoice->id,
                $invoice->tenant?->name ?? '(tenant terhapus)',
                $invoice->previous_seats ?? 0,
                $invoice->grants_seats ?? 0,
                $invoice->due_date?->toDateString() ?? '—',
            ));

            if (! $dryRun) {
                $settlement->settleIfFree($invoice);
            }
        }

        $this->info($dryRun
            ? sprintf('%d tagihan akan dilunasi (dry-run). Tidak ada yang diubah.', $stuck->count())
            : sprintf('%d tagihan dilunasi, seat-nya berlaku sekarang.', $stuck->count()));

        return self::SUCCESS;
    }
}

<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Umur tagihan terbuka dan apa yang terjadi sesudahnya ([BL-031]).
 *
 * Keputusan pemilik 2026-08-19: tagihan terbuka hidup **per hari** — 24 jam
 * sejak penjualannya terjadi. Lewat itu ia berhenti jadi tagihan hidup dan
 * dicatat sebagai **kas negatif**. Yang boleh membereskannya hanya pemilik,
 * dan hanya dari dashboard transaksi pemilik.
 *
 * **Kas negatif bukan pembatalan, dan bedanya menentukan stok.** Membatalkan
 * akan memulihkan stok — bersih di pembukuan, tapi bohong: barangnya sudah
 * keluar dan dibawa pelanggan. Karena itu `expire()` TIDAK menyentuh stok.
 * Stok baru kembali di `writeOff()`, yaitu ketika pemilik menyatakan tagihan
 * itu memang tak akan pernah dibayar — dan di sana pula kas negatifnya
 * ditutup.
 *
 * 24 jam dihitung dari tanggal EFEKTIF (`occurred_at` bila ada), bukan
 * `created_at`. Penjualan offline ber-`created_at` waktu sinkronisasi, jadi
 * `created_at` akan memberi tagihan kemarin umur yang baru lahir hari ini —
 * cacat yang sama yang sudah dibuktikan `[BL-028]` pada rekonsiliasi kas.
 */
class OpenBillExpiryService
{
    public function __construct(
        private StockService $stockService,
    ) {}

    /**
     * Pindahkan seluruh tagihan terbuka yang lewat 24 jam ke kas negatif.
     *
     * `withoutGlobalScopes()` disengaja: perintah terjadwal berjalan tanpa user
     * yang login, jadi TenantScope tidak punya tenant untuk disandarkan. Sapuan
     * ini memang lintas tenant — pola yang sama dengan ComputeTenantMonthlyRevenue.
     *
     * @param  mixed  $now  Titik acuan; null = sekarang. Ada demi pengujian dan
     *                      demi perhitungan ulang manual, bukan hiasan.
     * @return int Jumlah tagihan yang dipindahkan
     */
    public function expire(mixed $now = null, bool $dryRun = false): int
    {
        $query = Transaction::withoutGlobalScopes()->expiredOpenBills($now);

        if ($dryRun) {
            return $query->count();
        }

        $moved = 0;

        $query->orderBy('id')->chunkById(200, function ($bills) use (&$moved) {
            DB::transaction(function () use ($bills, &$moved) {
                foreach ($bills as $bill) {
                    $bill->update([
                        'status' => Transaction::STATUS_UNSETTLED,
                        'unsettled_at' => now(),

                        // WAJIB ikut. Tanpa ini timbunan `waiting` cuma
                        // berpindah sumber — persis timbunan yang pernah
                        // dibersihkan migrasi backfill_stale_fulfillment_status.
                        // Papan dapur memang sudah menyaring tanggal efektif
                        // hari ini, tapi satu lapis akan bocor lewat jalur yang
                        // belum ada hari ini; `void()` pun memakai dua lapis.
                        'fulfillment_status' => null,
                    ]);

                    $moved++;
                }
            });
        });

        return $moved;
    }

    /**
     * Pemilik menyatakan tagihan ini tak akan pernah dibayar.
     *
     * Inilah "jalur pembatalan sungguhan" yang disebut keputusan pemilik: di
     * sini — dan HANYA di sini — stok kembali, karena barangnya dianggap tidak
     * pernah jadi penjualan. Kas negatifnya ikut tertutup: transaksinya berhenti
     * berstatus `unsettled`.
     */
    public function writeOff(Transaction $transaction, User $owner): Transaction
    {
        if (! $transaction->isUnsettled()) {
            throw new \Exception('Hanya tagihan yang sudah jadi kas negatif yang bisa dihapuskan.');
        }

        if (! $owner->isOwner() || $owner->tenant_id !== $transaction->tenant_id) {
            throw new \Exception('Hanya pemilik yang dapat menghapuskan kas negatif.');
        }

        return DB::transaction(function () use ($transaction, $owner) {
            // Varian yang sudah dihapus tetap dilewati, bukan menggagalkan
            // seluruh penghapusan — pola yang sama dengan TransactionService::void().
            foreach ($transaction->items as $item) {
                $variant = $item->variant()->withTrashed()->first();

                if (! $variant) {
                    continue;
                }

                $this->stockService->restore($variant, $item->qty, $transaction->id);
            }

            $transaction->update([
                'status' => Transaction::STATUS_VOIDED,
                'fulfillment_status' => null,
                'edited_at' => now(),
                'edited_by' => $owner->id,
            ]);

            return $transaction->fresh();
        });
    }
}

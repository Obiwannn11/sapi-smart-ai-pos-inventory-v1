<?php

namespace App\Services;

use App\Models\CashDrawer;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi `transactions.cash_drawer_id` untuk baris yang lahir sebelum kolomnya
 * ada ([BL-028] Tahap B langkah 2).
 *
 * Inilah syarat yang membuat rekonsiliasi boleh membaca kolom itu: tanpa
 * backfill, setiap sesi yang lahir sebelum langkah 1 menghitung nol.
 *
 * **Aturannya menyalin turunan LAMA, bukan kebijakan pengisian maju.** Baris
 * lama dicocokkan persis seperti rekonsiliasi dulu menghitungnya — `user_id`
 * laci + tanggal efektif di dalam `opened_at`–`closed_at`. Dengan begitu
 * `expected_amount` yang sudah dibekukan saat tutup kas tetap cocok dengan
 * rekap yang dihitung ulang sesudah sakelar baca. Konsekuensinya disadari:
 * tagihan terbuka lama yang dilunasi kasir lain tetap jatuh ke laci
 * pembuatnya, karena memang ke sanalah angka lama menaruhnya.
 *
 * Dua pengecualian dari turunan lama, keduanya mengikuti `drawerReceiving()`:
 *
 *   self-order        tidak pernah ada uang yang masuk laci siapa pun
 *   `unsettled_at`    dilunasi pemilik sesudah 24 jam ([BL-031]); tidak ada
 *                     laci yang menerimanya
 *
 * Diperiksa 2026-09-15 pada data lokal: nol baris dari kedua jenis itu jatuh
 * di dalam jendela sesi mana pun, jadi pengecualiannya tidak menggeser angka
 * satu sesi pun.
 *
 * Transaksi yang jatuh di luar sesi mana pun dibiarkan `null` — tidak dipaksa
 * masuk laci terdekat. Baris yang sudah punya laci tidak pernah ditimpa, jadi
 * menjalankannya ulang aman.
 */
class CashDrawerAttributionBackfill
{
    /**
     * Status yang uangnya pernah menyentuh laci. `voided` ikut diisi dengan
     * alasan yang sama seperti `void()` tidak menghapus kolomnya: transaksi itu
     * memang pernah ada di laci tersebut. Rekonsiliasi tetap hanya menjumlahkan
     * yang `completed`.
     */
    private const STATUSES = [Transaction::STATUS_COMPLETED, Transaction::STATUS_VOIDED];

    /**
     * Sesi diproses dari yang paling BARU dibuka. Bila dua sesi milik kasir
     * yang sama tumpang-tindih, baris di irisannya jatuh ke sesi yang lebih
     * baru — jawaban yang sama dengan `CashDrawer::coveringAt()`.
     *
     * Pada dry-run, baris di irisan dua sesi seorang kasir terhitung dua kali.
     *
     * @return int Jumlah transaksi yang (akan) memperoleh laci
     */
    public function run(bool $dryRun = false): int
    {
        $attributed = 0;

        $drawers = CashDrawer::withoutGlobalScopes()
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->get(['id', 'tenant_id', 'user_id', 'opened_at', 'closed_at']);

        foreach ($drawers as $drawer) {
            $query = DB::table('transactions')
                ->where('tenant_id', $drawer->tenant_id)
                ->where('user_id', $drawer->user_id)
                ->whereNull('cash_drawer_id')
                ->whereIn('status', self::STATUSES)
                ->whereNull('unsettled_at')
                ->where(fn ($source) => $source
                    ->whereNull('source')
                    ->orWhere('source', '!=', Transaction::SOURCE_SELF_ORDER))
                ->whereRaw(Transaction::effectiveDateSql().' between ? and ?', [
                    $drawer->opened_at,
                    $drawer->closed_at ?? Carbon::now(),
                ]);

            $attributed += $dryRun
                ? $query->count()
                : $query->update(['cash_drawer_id' => $drawer->id]);
        }

        return $attributed;
    }
}

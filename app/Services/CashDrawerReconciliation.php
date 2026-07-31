<?php

namespace App\Services;

use App\Models\CashDrawer;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Menghitung berapa uang tunai yang SEHARUSNYA ada di sebuah laci kas.
 *
 * Satu-satunya sumber angka rekonsiliasi: dipakai bersama oleh pratinjau tutup
 * kas, `close()`, dan rekap sesi. Sebelumnya rumusnya hidup di dua tempat dan
 * pratinjaunya hanya membandingkan uang fisik dengan modal awal — kasir yang
 * menjual tunai sepanjang shift melihat "selisih" sebesar seluruh penjualannya
 * (lihat [BL-028]).
 *
 * Dua keputusan yang menentukan angkanya, dan keduanya mudah salah:
 *
 *   MILIK SIAPA — disaring `user_id` laci, bukan `tenant_id` saja. Versi lama
 *   menjumlahkan seluruh uang tunai TOKO ke tiap laci, jadi dua kasir yang
 *   shift bersamaan sama-sama mengaku memegang uang yang sama. Tidak pernah
 *   terlihat selama outlet hanya punya satu kasir — asumsi yang tidak pernah
 *   ditulis.
 *
 *   KAPAN — disaring tanggal EFEKTIF (`occurred_at` bila ada), bukan
 *   `created_at`. Penjualan offline disinkronkan belakangan; memakai
 *   `created_at` melemparkan uang yang masuk laci kemarin ke laci hari ini.
 *   Alasan yang sama sudah tertulis untuk papan antrian di
 *   TransactionService::assignQueuePosition().
 */
class CashDrawerReconciliation
{
    /**
     * Hanya tipe ini yang menyentuh laci fisik. QRIS & transfer masuk rekening,
     * bukan laci — memasukkannya membuat kasir mencari uang yang tidak ada.
     */
    private const TYPE_CASH = 'cash';

    /**
     * Rekonsiliasi satu sesi laci. Sesi yang masih terbuka dihitung sampai
     * `now()`, jadi hasilnya bergerak selama shift berjalan — pemanggilnya
     * yang bertanggung jawab mengambil ulang saat butuh angka terkini.
     *
     * @return array{
     *     opening_amount: float,
     *     cash_in: float,
     *     change_out: float,
     *     expected_amount: float,
     *     non_cash_in: float,
     *     transaction_count: int,
     *     payment_summary: array<int, array{name: string, type: string, total: float}>,
     * }
     */
    public function for(CashDrawer $drawer): array
    {
        $paymentSummary = $this->paymentSummary($drawer);

        $cashIn = $this->sumOfType($paymentSummary, cash: true);
        $nonCashIn = $this->sumOfType($paymentSummary, cash: false);
        $changeOut = (float) $this->transactionsOf($drawer)->sum('change_amount');
        $openingAmount = (float) $drawer->opening_amount;

        return [
            'opening_amount' => $openingAmount,
            'cash_in' => $cashIn,
            'change_out' => $changeOut,
            'expected_amount' => $openingAmount + $cashIn - $changeOut,
            'non_cash_in' => $nonCashIn,
            'transaction_count' => $this->transactionsOf($drawer)->count(),
            'payment_summary' => $paymentSummary,
        ];
    }

    /**
     * Transaksi yang uangnya masuk ke laci ini.
     *
     * `withoutGlobalScopes()` disengaja: TenantScope bergantung pada user yang
     * login, sedangkan service ini juga dipanggil dari konteks tanpa auth.
     * Nyatakan penyaringnya sendiri alih-alih menumpang kebetulan — pola yang
     * sama dengan ComputeTenantMonthlyRevenue.
     */
    private function transactionsOf(CashDrawer $drawer): Builder
    {
        [$from, $to] = $this->window($drawer);

        return Transaction::withoutGlobalScopes()
            ->where('tenant_id', $drawer->tenant_id)
            ->where('user_id', $drawer->user_id)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereEffectiveBetween($from, $to);
    }

    /**
     * Rekap uang masuk per metode pembayaran.
     *
     * Memakai join, bukan `whereHas`: subquery `whereHas` akan menyeret
     * TenantScope milik Transaction ikut masuk, dan itu justru yang sedang
     * dihindari di transactionsOf().
     *
     * @return array<int, array{name: string, type: string, total: float}>
     */
    private function paymentSummary(CashDrawer $drawer): array
    {
        [$from, $to] = $this->window($drawer);

        return TransactionPayment::query()
            ->join('transactions', 'transaction_payments.transaction_id', '=', 'transactions.id')
            ->join('payment_methods', 'transaction_payments.payment_method_id', '=', 'payment_methods.id')
            ->where('transactions.tenant_id', $drawer->tenant_id)
            ->where('transactions.user_id', $drawer->user_id)
            ->where('transactions.status', Transaction::STATUS_COMPLETED)
            ->whereRaw(Transaction::effectiveDateSql().' between ? and ?', [$from, $to])
            ->selectRaw('payment_methods.name, payment_methods.type, SUM(transaction_payments.amount) as total')
            ->groupBy('payment_methods.name', 'payment_methods.type')
            ->get()
            ->map(fn ($row) => [
                'name' => (string) $row->name,
                'type' => (string) $row->type,
                'total' => (float) $row->total,
            ])
            ->all();
    }

    /**
     * @param  array<int, array{name: string, type: string, total: float}>  $paymentSummary
     */
    private function sumOfType(array $paymentSummary, bool $cash): float
    {
        return (float) collect($paymentSummary)
            ->filter(fn (array $row) => ($row['type'] === self::TYPE_CASH) === $cash)
            ->sum('total');
    }

    /**
     * Batas sesi. Laci yang masih terbuka dihitung sampai sekarang.
     *
     * Batas yang disadari: penjualan offline yang TERJADI di dalam sesi tapi
     * baru tersinkron setelah lacinya ditutup tidak akan terhitung di mana pun
     * — `expected_amount` sesi itu sudah dibekukan. Memakai `created_at` tidak
     * menyelesaikannya, hanya memindahkan kesalahannya ke laci yang salah.
     * Jawaban sebenarnya menunggu `cash_drawer_id` di Tahap B [BL-028].
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(CashDrawer $drawer): array
    {
        return [$drawer->opened_at, $drawer->closed_at ?? Carbon::now()];
    }
}

<?php

namespace App\Services;

use App\Models\CashDrawer;
use App\Models\CashDrawerMovement;
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
 *
 * **Kolom `transactions.cash_drawer_id` sudah ada dan sudah terisi, tapi
 * SENGAJA belum dibaca di sini** ([BL-028] Tahap B langkah 1). Ia hanya terisi
 * untuk penjualan yang lahir sesudah migrasinya; membacanya sekarang akan
 * membuat 248 sesi lama menghitung nol. Sakelar bacanya adalah langkah 2, dan
 * syaratnya salah satu dari — seluruh sesi yang masih hidup lahir sesudah
 * migrasi itu, atau backfill benar-benar dijalankan. Sampai itu terjadi,
 * turunan `user_id` + jendela di bawah ini tetap satu-satunya jawaban; ia
 * bukan sisa yang terlewat.
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
     *     unsettled_cash: float,
     *     unsettled_count: int,
     *     movement_net: float,
     *     payout_total: float,
     *     deposit_total: float,
     *     pending_payout_total: float,
     *     pending_deposit_total: float,
     *     pending_movement_count: int,
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
        $unsettled = $this->unsettledOf($drawer);
        $movements = $this->movementsOf($drawer);

        $approved = $movements->where('status', CashDrawerMovement::STATUS_APPROVED);
        $pending = $movements->where('status', CashDrawerMovement::STATUS_PENDING);

        $payoutTotal = (float) $approved->where('type', CashDrawerMovement::TYPE_PAYOUT)->sum('amount');
        $depositTotal = (float) $approved->where('type', CashDrawerMovement::TYPE_DEPOSIT)->sum('amount');
        $movementNet = $depositTotal - $payoutTotal;

        return [
            'opening_amount' => $openingAmount,
            'cash_in' => $cashIn,
            'change_out' => $changeOut,
            // Mutasi yang SUDAH disetujui ikut rumusnya; yang masih menunggu
            // sengaja tidak ([BL-087]). Lihat `movementsOf()`.
            'expected_amount' => $openingAmount + $cashIn - $changeOut + $movementNet,
            'movement_net' => $movementNet,
            'payout_total' => $payoutTotal,
            'deposit_total' => $depositTotal,
            'pending_payout_total' => (float) $pending->where('type', CashDrawerMovement::TYPE_PAYOUT)->sum('amount'),
            'pending_deposit_total' => (float) $pending->where('type', CashDrawerMovement::TYPE_DEPOSIT)->sum('amount'),
            'pending_movement_count' => $pending->count(),
            'non_cash_in' => $nonCashIn,
            'transaction_count' => $this->transactionsOf($drawer)->count(),
            'unsettled_cash' => (float) $unsettled->sum('total_amount'),
            'unsettled_count' => $unsettled->count(),
            'payment_summary' => $paymentSummary,
        ];
    }

    /**
     * Uang keluar-masuk laci di luar penjualan ([BL-087]).
     *
     * **Hanya yang berstatus `approved` yang masuk `expected_amount`.** Yang
     * masih menunggu tampil sebagai baris tersendiri — pola yang sama persis
     * dengan kas negatif di bawah ini: terlihat, dipertanggungjawabkan, tapi
     * belum menggerakkan angka. Tanpa penahan itu, kasir yang lacinya kurang
     * tinggal mencatat pengeluaran sebesar kekurangannya dan selisihnya jadi
     * nol — `[BL-086]` dibatalkan dari sisi sebaliknya.
     *
     * Yang `rejected` tidak dihitung di mana pun kecuali sebagai riwayat.
     *
     * Tidak disaring jendela waktu: mutasi menempel langsung pada
     * `cash_drawer_id`, jadi kepemilikannya eksplisit dan tidak perlu
     * diturunkan dari `user_id` + rentang jam seperti transaksi. Inilah yang
     * dulu diminta `[BL-028]` Tahap B untuk transaksi, dan di sini ia gratis
     * karena tabelnya lahir sesudah pelajaran itu.
     *
     * @return \Illuminate\Support\Collection<int, CashDrawerMovement>
     */
    private function movementsOf(CashDrawer $drawer): \Illuminate\Support\Collection
    {
        return CashDrawerMovement::withoutGlobalScopes()
            ->where('cash_drawer_id', $drawer->id)
            ->get();
    }

    /**
     * Kas negatif yang jatuh ke sesi ini: tagihan terbuka milik kasir ini yang
     * lewat umurnya di tengah shiftnya ([BL-031]).
     *
     * **Sengaja TIDAK masuk `expected_amount`.** Rumus itu menjawab satu
     * pertanyaan saja — berapa uang fisik yang seharusnya ada di laci — dan
     * uang tagihan ini tidak pernah masuk laci mana pun. Menambahkannya akan
     * membuat kasir tampak kurang sebesar tagihan yang bukan ia pegang, persis
     * jenis angka salah yang [BL-028] baru saja perbaiki. Ia muncul sebagai
     * baris tersendiri: terlihat, dipertanggungjawabkan, tapi bukan selisih
     * laci.
     *
     * Penyaringnya `unsettled_at`, bukan tanggal efektif penjualan: tagihannya
     * memang lahir di shift lain — kemarin, menurut definisi 24 jam — dan yang
     * jatuh ke sesi ini adalah SAAT ia berhenti bisa ditagih.
     *
     * Batas yang disadari: bila kasir itu tidak sedang membuka laci saat
     * sapuan berjalan, kas negatifnya tidak muncul di sesi mana pun. Ia tetap
     * terlihat penuh di dashboard transaksi pemilik, dan di sanalah satu-satunya
     * tempat ia boleh dibereskan.
     *
     * @return \Illuminate\Support\Collection<int, Transaction>
     */
    private function unsettledOf(CashDrawer $drawer): \Illuminate\Support\Collection
    {
        [$from, $to] = $this->window($drawer);

        return Transaction::withoutGlobalScopes()
            ->where('tenant_id', $drawer->tenant_id)
            ->where('user_id', $drawer->user_id)
            ->where('status', Transaction::STATUS_UNSETTLED)
            ->whereBetween('unsettled_at', [$from, $to])
            ->get(['id', 'code', 'total_amount']);
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

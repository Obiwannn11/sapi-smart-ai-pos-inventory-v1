<?php

namespace App\Services;

use App\Models\TransactionPayment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rekap pendapatan per metode pembayaran — satu-satunya sumbernya.
 *
 * Yang diperbaiki di sini ([BL-109]): `transaction_payments.amount` menyimpan
 * uang yang DISERAHKAN pelanggan, bukan yang dibayarkan. Selembar Rp 100.000
 * untuk belanja Rp 39.000 tercatat 100.000, dan Rp 61.000 di antaranya kembali
 * ke tangan pelanggan sebagai kembalian. Empat layar menjumlahkan suku pertama
 * tanpa pernah mengurangkan `transactions.change_amount` — beranda, laporan
 * harian, laporan bulanan berikut CSV-nya, dan rekap sesi kas di aplikasi
 * mobile — sehingga omzet tunai selalu dilaporkan lebih besar dari yang
 * sebenarnya masuk.
 *
 * Ia lahir sebagai pembaca bersama, bukan empat perbaikan sejajar, karena
 * rumusnya identik di keempat tempat dan salinan yang berumur akan berbeda di
 * salah satunya. `CashDrawerReconciliation` sengaja tetap punya kueri sendiri:
 * ia menyaring per KASIR di dalam jendela sesi, bukan per tenant di dalam
 * periode, dan `expected_amount` miliknya sudah mengurangkan kembalian dengan
 * benar sejak awal.
 *
 * **Periodenya milik pemanggil.** Yang dioper ke sini kueri transaksi yang
 * sudah tersaring lengkap — status, tenant, rentang tanggal — dan rekapnya
 * diturunkan dari kueri itu juga. Dengan begitu rekap dan angka omzet di
 * layar yang sama tidak mungkin menjawab periode yang berbeda; sebelumnya
 * keduanya membangun penyaringnya sendiri-sendiri.
 */
class PaymentMethodRecap
{
    /**
     * Hanya tipe ini yang mengenal kembalian. QRIS dan transfer dibayar pas —
     * tidak ada uang yang dikembalikan, jadi tidak ada yang perlu dikurangkan.
     */
    private const TYPE_CASH = 'cash';

    /**
     * @param  Builder<\App\Models\Transaction>  $transactions  Kueri transaksi yang sudah tersaring periode dan statusnya
     * @return array<int, array{id: int, name: string, type: string, total: float}>
     */
    public function for(Builder $transactions, int $tenantId): array
    {
        $rows = TransactionPayment::query()
            ->selectRaw('payment_methods.id, payment_methods.name, payment_methods.type, SUM(transaction_payments.amount) as total')
            // Penjaga tenant ditaruh di klausa join, bukan di `where`: metode
            // pembayaran milik tenant lain harus membuat barisnya hilang, bukan
            // membuat seluruh rekap kosong.
            ->join('payment_methods', function ($join) use ($tenantId) {
                $join->on('transaction_payments.payment_method_id', '=', 'payment_methods.id')
                    ->where('payment_methods.tenant_id', $tenantId);
            })
            ->whereIn(
                'transaction_payments.transaction_id',
                (clone $transactions)->select('transactions.id')
            )
            // Dikelompokkan per `id`, bukan per nama. Nama yang dibekukan di
            // baris bisa sama untuk dua metode yang berbeda, dan menggabungkan
            // keduanya jadi satu baris menyembunyikan salah satunya — pelajaran
            // yang sama dengan rincian varian di laporan bulanan.
            ->groupBy('payment_methods.id', 'payment_methods.name', 'payment_methods.type')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'type' => (string) $row->type,
                'total' => (float) $row->total,
            ])
            ->all();

        $rows = $this->subtractChange(
            $rows,
            (float) (clone $transactions)->sum('change_amount')
        );

        // Terbesar dulu. Urutannya ditentukan SESUDAH kembalian dikurangkan,
        // bukan oleh `ORDER BY` di dalam kueri: metode yang tampak terbesar
        // sebelum dikurangkan belum tentu yang terbesar sesudahnya, dan yang
        // dibaca owner adalah angka yang sudah dikurangkan.
        usort($rows, fn (array $a, array $b) => $b['total'] <=> $a['total']);

        return $rows;
    }

    /**
     * Kembalian dikurangkan dari baris TUNAI saja.
     *
     * Menyebarnya rata ke seluruh metode akan mengecilkan QRIS dan transfer
     * yang tidak pernah mengembalikan uang sepeser pun. Bila tenant punya lebih
     * dari satu metode tunai, kembalian dibagi menurut porsi masing-masing —
     * yang untuk satu metode tunai (keadaan setiap tenant hari ini) berarti
     * seluruhnya jatuh ke sana.
     *
     * Dua penjaga di depan, dan keduanya bukan hiasan: transaksi tanpa
     * kembalian tidak perlu disentuh, dan kembalian tanpa baris tunai sama
     * sekali tidak punya tempat untuk dikurangkan. Yang kedua hanya mungkin
     * lahir dari data yang sudah janggal sejak awal (kembalian dari pembayaran
     * non-tunai), dan membaginya ke baris non-tunai akan mengarang angka baru
     * alih-alih membiarkan kejanggalannya terlihat.
     *
     * @param  array<int, array{id: int, name: string, type: string, total: float}>  $rows
     * @return array<int, array{id: int, name: string, type: string, total: float}>
     */
    private function subtractChange(array $rows, float $changeOut): array
    {
        if ($changeOut <= 0) {
            return $rows;
        }

        $cashTotal = (float) collect($rows)
            ->filter(fn (array $row) => $row['type'] === self::TYPE_CASH)
            ->sum('total');

        if ($cashTotal <= 0) {
            return $rows;
        }

        return collect($rows)
            ->map(function (array $row) use ($changeOut, $cashTotal) {
                if ($row['type'] !== self::TYPE_CASH) {
                    return $row;
                }

                $row['total'] = round($row['total'] - ($changeOut * $row['total'] / $cashTotal), 2);

                return $row;
            })
            ->all();
    }
}

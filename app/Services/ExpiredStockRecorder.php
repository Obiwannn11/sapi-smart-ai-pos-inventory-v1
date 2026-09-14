<?php

namespace App\Services;

use App\Models\ExpiredStockRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menstempel barang yang melewati kedaluwarsanya dengan stok tersisa ([BL-105]).
 *
 * **Kenapa pencatat, dan bukan sebuah kueri.** `StockRescueService::spoiled()`
 * sudah menjawab "berapa yang basi di rak SEKARANG", dan itu jujur. Yang tidak
 * bisa dijawabnya adalah "berapa yang basi bulan lalu" — karena `stock` menyimpan
 * nilai sekarang, bukan sejarah. Begitu pemilik membuang barangnya dan menyetel
 * stok jadi nol, kerugiannya lenyap tanpa jejak, dan tidak ada jenis
 * `stock_movements` yang berarti "dibuang" untuk menemukannya kembali. Sekali
 * lewat, angka itu hilang selamanya. Karena itu pencatat ini tidak bisa ditunda
 * sampai ada yang membacanya.
 *
 * **Kenapa 00:05.** Sebuah varian yang kedaluwarsa tanggal X masih SAH DIJUAL
 * sepanjang tanggal X — `DiscountService` baru menolaknya lewat tengah malam,
 * dan potongan `near_expiry` justru paling dalam di hari itu. Ia baru jadi
 * barang basi pada X+1 pukul 00:00. Mengamatinya lima menit sesudah itu
 * menangkap keadaan rak yang benar dan menutup hampir seluruh jendela di mana
 * pemilik sempat membuangnya lebih dulu. Sapuan siang hari akan melewatkan
 * setiap barang yang dibuang pagi harinya, diam-diam, dan angka kerugiannya
 * akan selalu terlalu kecil tanpa ada yang tahu.
 *
 * **Yang dibaca adalah batch, bukan varian** ([BL-111]). Satu varian bisa
 * menyimpan beberapa tanggal kedaluwarsa sekaligus, dan tiap tanggal yang
 * lewat melahirkan barisnya sendiri dengan jumlah unit milik tanggal itu saja.
 * Sebelum batch ada, varian dengan 20 unit basi April dan 30 unit segar Agustus
 * tidak pernah tercatat basi sama sekali sampai Agustus — lalu tercatat 50.
 *
 * **Yang TIDAK dilakukannya:** ia tidak menyentuh stok, tidak menonaktifkan
 * varian, dan tidak memberi tahu siapa pun. Ia hanya mencatat. Membuang barang
 * basi tetap keputusan pemilik, dan `[BL-105]` butir 3 yang akan memberitahunya.
 */
class ExpiredStockRecorder
{
    /**
     * Catat setiap (varian, tanggal kedaluwarsa) yang basi dan belum punya barisnya.
     *
     * `$today` ada demi pengujian dan demi menjalankan ulang hari yang terlewat
     * ketika `schedule:run` mati semalam — bukan hiasan. Menjalankannya untuk
     * hari kemarin tetap benar selama tanggal yang sama belum tercatat, karena
     * kuncinya `(varian, tanggal kedaluwarsa)`, bukan tanggal sapuan.
     *
     * Perhatikan bahwa yang distempel adalah sisa batch pada SAAT PENGAMATAN,
     * bukan saat kedaluwarsa — dan untuk sapuan yang terlambat berhari-hari
     * keduanya bisa berbeda. Itu ongkos yang diterima sadar: satu angka yang
     * sedikit terlalu kecil masih jauh lebih baik daripada tidak ada angka sama
     * sekali.
     *
     * @param  string|null  $today  Hari toko sebagai acuan; null = hari ini.
     * @return int Jumlah baris yang ditulis (atau akan ditulis, bila dry-run)
     */
    public function record(?string $today = null, bool $dryRun = false): int
    {
        $today ??= BusinessClock::today();

        $groups = $this->unrecordedExpired($today);

        if ($dryRun) {
            return $groups->count();
        }

        $written = 0;

        foreach ($groups->chunk(200) as $chunk) {
            $rows = $chunk->map(function (object $group) use ($today) {
                $qty = (int) $group->qty;
                $costPrice = (float) $group->cost_price;

                return [
                    'tenant_id' => $group->tenant_id,
                    'product_variant_id' => $group->product_variant_id,
                    'label' => $this->labelFor($group->product_name, $group->variant_name),
                    'expiry_date' => $group->expiry_day,
                    'recorded_on' => $today,
                    'qty' => $qty,
                    'cost_price' => $costPrice,
                    'value' => $qty * $costPrice,
                    'source' => ExpiredStockRecord::SOURCE_RECORDER,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->values()->all();

            // insertOrIgnore, bukan insert: indeks unik `(varian, kedaluwarsa)`
            // adalah penjaga terakhir kalau dua sapuan berjalan bersamaan, dan
            // tabrakan di sana tidak boleh menggagalkan sisa rombongannya.
            DB::transaction(function () use ($rows, &$written) {
                $written += ExpiredStockRecord::insertOrIgnore($rows);
            });
        }

        return $written;
    }

    /**
     * Sisa batch basi yang belum tercatat, dijumlahkan per (varian, tanggal).
     *
     * Dua batch dengan tanggal kedaluwarsa yang sama (dua kiriman berbeda hari
     * dari satu produksi) jadi SATU baris: kuncinya `(varian, tanggal)`, sama
     * dengan indeks unik tabelnya.
     *
     * Saringan "belum tercatat" juga atas `(varian, tanggal)`, bukan atas
     * variannya saja — varian yang direstok dengan tanggal baru memang HARUS
     * tercatat lagi ketika tanggal itu lewat. Dibandingkan lewat `DATE()` karena
     * kolom tanggal di SQLite bisa tersimpan dengan atau tanpa jam.
     *
     * Varian dan produk yang sudah dihapus tidak ikut, persis perilaku sebelum
     * batch ada.
     *
     * @return Collection<int, object>
     */
    private function unrecordedExpired(string $today): Collection
    {
        return DB::table('product_stock_batches')
            ->join('product_variants', 'product_variants.id', '=', 'product_stock_batches.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNull('product_variants.deleted_at')
            ->whereNull('products.deleted_at')
            ->where('product_stock_batches.qty_remaining', '>', 0)
            ->whereNotNull('product_stock_batches.expiry_date')
            ->whereDate('product_stock_batches.expiry_date', '<', $today)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('expired_stock_records')
                ->whereColumn('expired_stock_records.product_variant_id', 'product_stock_batches.product_variant_id')
                ->whereRaw('DATE(expired_stock_records.expiry_date) = DATE(product_stock_batches.expiry_date)'))
            ->groupBy(
                'products.tenant_id',
                'product_stock_batches.product_variant_id',
                'products.name',
                'product_variants.name',
                'product_variants.cost_price',
            )
            ->groupByRaw('DATE(product_stock_batches.expiry_date)')
            ->orderBy('product_stock_batches.product_variant_id')
            ->get([
                'products.tenant_id',
                'product_stock_batches.product_variant_id',
                'products.name as product_name',
                'product_variants.name as variant_name',
                'product_variants.cost_price',
                DB::raw('DATE(product_stock_batches.expiry_date) as expiry_day'),
                DB::raw('SUM(product_stock_batches.qty_remaining) as qty'),
            ]);
    }

    /**
     * Nama yang dibaca pemilik, dirakit sama dengan strip saran jual.
     */
    private function labelFor(?string $productName, string $variantName): string
    {
        return $productName === null ? $variantName : $productName.' - '.$variantName;
    }
}

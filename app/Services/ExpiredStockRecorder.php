<?php

namespace App\Services;

use App\Models\ExpiredStockRecord;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
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
 * **Yang TIDAK dilakukannya:** ia tidak menyentuh stok, tidak menonaktifkan
 * varian, dan tidak memberi tahu siapa pun. Ia hanya mencatat. Membuang barang
 * basi tetap keputusan pemilik, dan `[BL-105]` butir 3 yang akan memberitahunya.
 */
class ExpiredStockRecorder
{
    /**
     * Catat setiap varian yang basi dan belum punya barisnya.
     *
     * `$today` ada demi pengujian dan demi menjalankan ulang hari yang terlewat
     * ketika `schedule:run` mati semalam — bukan hiasan. Menjalankannya untuk
     * hari kemarin tetap benar selama varian yang sama belum tercatat, karena
     * kuncinya `(varian, tanggal kedaluwarsa)`, bukan tanggal sapuan.
     *
     * Perhatikan bahwa yang distempel adalah stok pada SAAT PENGAMATAN, bukan
     * saat kedaluwarsa — dan untuk sapuan yang terlambat berhari-hari keduanya
     * bisa berbeda. Itu ongkos yang diterima sadar: satu angka yang sedikit
     * terlalu kecil masih jauh lebih baik daripada tidak ada angka sama sekali.
     *
     * @param  string|null  $today  Hari toko sebagai acuan; null = hari ini.
     * @return int Jumlah baris yang ditulis (atau akan ditulis, bila dry-run)
     */
    public function record(?string $today = null, bool $dryRun = false): int
    {
        $today ??= BusinessClock::today();

        $query = $this->unrecordedExpired($today);

        if ($dryRun) {
            return $query->count();
        }

        $written = 0;

        $query->chunkById(200, function ($variants) use ($today, &$written) {
            $rows = [];

            foreach ($variants as $variant) {
                $costPrice = (float) $variant->cost_price;

                $rows[] = [
                    'tenant_id' => $variant->product->tenant_id,
                    'product_variant_id' => $variant->id,
                    'label' => $this->labelFor($variant),
                    'expiry_date' => $variant->expiry_date->toDateString(),
                    'recorded_on' => $today,
                    'qty' => $variant->stock,
                    'cost_price' => $costPrice,
                    'value' => $variant->stock * $costPrice,
                    'source' => ExpiredStockRecord::SOURCE_RECORDER,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($rows === []) {
                return;
            }

            // insertOrIgnore, bukan insert: indeks unik `(varian, kedaluwarsa)`
            // adalah penjaga terakhir kalau dua sapuan berjalan bersamaan, dan
            // tabrakan di sana tidak boleh menggagalkan sisa rombongannya.
            DB::transaction(function () use ($rows, &$written) {
                $written += ExpiredStockRecord::insertOrIgnore($rows);
            });
        });

        return $written;
    }

    /**
     * Varian yang sudah lewat kedaluwarsanya, masih bersisa, dan belum tercatat.
     *
     * `whereDoesntHave`-nya menyaring atas `(varian, tanggal kedaluwarsa)` dan
     * bukan atas variannya saja — sebuah varian yang direstok dengan tanggal
     * kedaluwarsa baru memang HARUS tercatat lagi ketika tanggal itu lewat.
     * Menyaring per varian akan membuat tiap varian hanya bisa basi sekali
     * seumur hidupnya, dan barang yang paling sering basi justru yang paling
     * sering direstok.
     *
     * Tidak memakai `withoutGlobalScopes()` meski ini sapuan lintas tenant, dan
     * itu disengaja: `ProductVariant` tidak punya `TenantScope` (tenantnya lewat
     * `products`), sedangkan scope yang ADA di sana adalah SoftDeletes. Membuang
     * seluruh scope berarti ikut menstempel varian yang sudah dihapus.
     *
     * @return Builder<ProductVariant>
     */
    private function unrecordedExpired(string $today): Builder
    {
        return ProductVariant::query()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', $today)
            ->where('stock', '>', 0)
            ->whereHas('product')
            ->whereDoesntHave('expiredStockRecords', fn ($query) => $query
                ->whereColumn('expired_stock_records.expiry_date', 'product_variants.expiry_date'))
            ->with('product:id,name,tenant_id');
    }

    /**
     * Nama yang dibaca pemilik, dirakit sama dengan strip saran jual.
     */
    private function labelFor(ProductVariant $variant): string
    {
        $productName = $variant->product?->name;

        return $productName === null ? $variant->name : $productName.' - '.$variant->name;
    }
}

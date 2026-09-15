<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStockBatch;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Buku batch stok: dari kedatangan mana setiap unit berasal, dan kapan ia basi
 * ([BL-111]).
 *
 * **`product_variants.stock` tetap angka yang dibaca seluruh aplikasi.** Kelas
 * ini menjaga satu kesetaraan di bawahnya: jumlah `qty_remaining` seluruh batch
 * sebuah varian = `max(stock, 0)`. Stok boleh minus (penjualan offline yang
 * mendahului datanya), batch tidak.
 *
 * **Urutan pengambilan: FEFO di antara barang yang MASIH BAIK, lalu barulah
 * yang basi.** Bukan FEFO murni. FEFO murni akan menjual batch yang sudah lewat
 * tanggalnya lebih dulu — tepat barang yang tidak boleh keluar tanpa ditanya.
 * Barang basi hanya diambil kalau yang baik tidak cukup, dan hanya kalau
 * pemanggilnya menyatakan sudah ada yang mengonfirmasi ([BL-108]). Batch tanpa
 * tanggal diambil paling akhir di antara yang baik: ia tidak berpacu dengan
 * waktu.
 *
 * **`product_variants.expiry_date` jadi turunan:** tanggal paling awal di antara
 * batch yang masih bersisa, termasuk yang sudah basi. Dengan begitu setiap
 * pembaca lama — potongan `near_expiry`, saran barang tertekan, ember stok —
 * tetap membaca satu tanggal yang jujur tanpa perlu diubah, dan varian yang
 * masih menyimpan batch basi tidak dipromosikan sampai batch itu dibuang.
 *
 * Penulis yang melewati kelas ini (formulir varian, seeder yang menulis
 * `stock` dengan query builder) tidak merusak apa pun secara permanen:
 * {@see self::reconcile()} dipanggil sebelum dan sesudah setiap mutasi dan
 * menutup selisihnya. Tapi setiap selisih yang ditutup begitu kehilangan
 * tanggal aslinya, jadi jalur yang benar tetap lewat `StockService`.
 */
class StockBatchService
{
    /**
     * Stok awal varian yang baru lahir jadi batch `opening`.
     *
     * Dipanggil dari hook `created` di `ProductVariant`, sehingga formulir
     * produk, factory, dan seeder yang memakai Eloquent semuanya melahirkan
     * batchnya tanpa ada yang perlu mengingatnya.
     */
    public function open(ProductVariant $variant): void
    {
        $stock = (int) $variant->stock;

        if ($stock <= 0) {
            return;
        }

        $this->receive(
            $variant,
            $stock,
            $variant->expiry_date?->toDateString(),
            ProductStockBatch::SOURCE_OPENING,
        );
    }

    /**
     * Terima barang sebagai batch baru. Tidak pernah menimpa batch lama.
     */
    public function receive(
        ProductVariant $variant,
        int $qty,
        ?string $expiryDate,
        string $source,
        ?StockMovement $movement = null,
    ): ProductStockBatch {
        $batch = ProductStockBatch::create([
            'tenant_id' => $this->tenantIdOf($variant),
            'product_variant_id' => $variant->id,
            'expiry_date' => $expiryDate,
            'qty_received' => $qty,
            'qty_remaining' => $qty,
            'received_at' => now(),
            'source' => $source,
        ]);

        $this->link($movement, $batch, $qty);

        return $batch;
    }

    /**
     * Ambil `$qty` unit dari batch-batch varian ini.
     *
     * @param  bool  $allowExpired  sudah ada manusia yang menyatakan "tetap jual";
     *                              tanpanya, kekurangan barang baik adalah penolakan
     * @param  string|null  $asOf  hari toko acuan "sudah basi"; penjualan offline
     *                             memakai hari kejadiannya, bukan hari sinkronisasi
     * @param  bool  $expiredFirst  untuk MEMBUANG barang (koreksi turun), bukan menjual:
     *                              yang dibuang orang dari rak adalah yang basi
     * @return array{expired_qty: int, earliest_expired: string|null, unallocated: int}
     *
     * @throws \Exception bila barang basi dibutuhkan tapi belum dikonfirmasi
     */
    public function take(
        ProductVariant $variant,
        int $qty,
        ?StockMovement $movement = null,
        bool $allowExpired = false,
        ?string $asOf = null,
        bool $expiredFirst = false,
    ): array {
        $today = $asOf ?? BusinessClock::today();

        [$expired, $fresh] = $this->remainingBatches($variant, lock: true)
            ->partition(fn (ProductStockBatch $batch) => $batch->isExpiredOn($today));

        $freshQty = (int) $fresh->sum('qty_remaining');

        if (! $allowExpired && $qty > $freshQty && $expired->isNotEmpty()) {
            throw new \Exception($this->expiredRefusal($variant, $freshQty, $qty));
        }

        $order = $expiredFirst ? $expired->concat($fresh) : $fresh->concat($expired);

        $left = $qty;
        $expiredQty = 0;
        $earliestExpired = null;

        foreach ($order as $batch) {
            if ($left <= 0) {
                break;
            }

            $taken = min($left, $batch->qty_remaining);
            $batch->qty_remaining -= $taken;
            $batch->save();

            $this->link($movement, $batch, -$taken);

            if ($batch->isExpiredOn($today)) {
                $expiredQty += $taken;
                $date = $batch->expiry_date->toDateString();
                $earliestExpired = $earliestExpired === null ? $date : min($earliestExpired, $date);
            }

            $left -= $taken;
        }

        return [
            'expired_qty' => $expiredQty,
            'earliest_expired' => $earliestExpired,
            'unallocated' => $left,
        ];
    }

    /**
     * Kembalikan unit ke batch yang dulu melepasnya untuk transaksi ini.
     *
     * Terakhir diambil, pertama dikembalikan. Karena penjualan mengambil
     * barang baik lebih dulu, unit basi yang ikut terjual kembali ke batch
     * basinya — bukan menyamar jadi stok baik yang bisa dijual lagi tanpa
     * ditanya.
     *
     * Penjualan dari sebelum buku batch ini ada tidak punya jejak; unitnya
     * kembali sebagai batch `reconcile` bertanggal kedaluwarsa varian saat ini.
     */
    public function giveBack(ProductVariant $variant, int $qty, int $transactionId, ?StockMovement $movement = null): void
    {
        $taken = DB::table('stock_movement_batches')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_batches.stock_movement_id')
            ->where('stock_movements.product_variant_id', $variant->id)
            ->where('stock_movements.reference_id', $transactionId)
            ->whereIn('stock_movements.type', [
                StockMovement::TYPE_SALE,
                StockMovement::TYPE_EDIT,
                StockMovement::TYPE_VOID,
            ])
            ->groupBy('stock_movement_batches.product_stock_batch_id')
            ->havingRaw('SUM(stock_movement_batches.qty) < 0')
            ->selectRaw('stock_movement_batches.product_stock_batch_id as batch_id')
            ->selectRaw('-SUM(stock_movement_batches.qty) as outstanding')
            ->selectRaw('MAX(stock_movement_batches.id) as last_link')
            ->orderByDesc('last_link')
            ->get();

        $batches = ProductStockBatch::whereIn('id', $taken->pluck('batch_id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $left = $qty;

        foreach ($taken as $row) {
            $batch = $batches->get($row->batch_id);

            if ($left <= 0 || $batch === null) {
                continue;
            }

            $returned = min($left, (int) $row->outstanding);
            $batch->qty_remaining += $returned;
            $batch->save();

            $this->link($movement, $batch, $returned);

            $left -= $returned;
        }

        if ($left > 0) {
            $this->receive(
                $variant,
                $left,
                $this->currentExpiryOf($variant),
                ProductStockBatch::SOURCE_RECONCILE,
                $movement,
            );
        }
    }

    /**
     * Koreksi naik: unitnya ditumpangkan ke batch yang paling akhir datang.
     *
     * Barang yang "ternyata masih ada" saat dihitung hampir selalu sisa
     * kiriman terakhir. Membuatnya batch tanpa tanggal justru lebih salah —
     * ia akan dijual paling akhir dan tidak pernah terhitung basi.
     */
    public function putBack(ProductVariant $variant, int $qty, ?StockMovement $movement = null): void
    {
        $batch = ProductStockBatch::where('product_variant_id', $variant->id)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($batch === null) {
            $this->receive($variant, $qty, null, ProductStockBatch::SOURCE_ADJUSTMENT, $movement);

            return;
        }

        $batch->qty_remaining += $qty;
        $batch->save();

        $this->link($movement, $batch, $qty);
    }

    /**
     * Samakan jumlah batch dengan `stock` varian.
     *
     * Kurang → selisihnya jadi batch `reconcile` bertanggal kedaluwarsa varian
     * saat ini, satu-satunya tanggal yang diketahui. Lebih → dikurangi dengan
     * urutan penjualan biasa; kelebihan itu adalah unit yang sudah keluar
     * lebih dulu daripada catatannya (penjualan offline yang membuat stok
     * minus lalu direstok).
     */
    public function reconcile(ProductVariant $variant): void
    {
        $target = max(0, (int) ProductVariant::withTrashed()->whereKey($variant->id)->value('stock'));

        $held = (int) ProductStockBatch::where('product_variant_id', $variant->id)
            ->remaining()
            ->sum('qty_remaining');

        if ($held < $target) {
            $this->receive(
                $variant,
                $target - $held,
                $this->currentExpiryOf($variant),
                ProductStockBatch::SOURCE_RECONCILE,
            );
        } elseif ($held > $target) {
            $this->take($variant, $held - $target, allowExpired: true);
        }
    }

    /**
     * Tulis ulang `product_variants.expiry_date` dari batch yang tersisa.
     *
     * Varian yang batchnya sudah habis semua dibiarkan memegang tanggal
     * terakhirnya. Menolkannya akan membuat void atas penjualan lama
     * mengembalikan barangnya tanpa tanggal.
     *
     * Ditulis lewat query builder, tanpa event model: ini cache, bukan
     * perubahan yang dibuat seseorang.
     */
    public function refreshExpiry(ProductVariant $variant): void
    {
        $remaining = ProductStockBatch::where('product_variant_id', $variant->id)->remaining();

        if (! (clone $remaining)->exists()) {
            return;
        }

        $earliest = (clone $remaining)->whereNotNull('expiry_date')->min('expiry_date');
        $earliest = $earliest === null ? null : substr((string) $earliest, 0, 10);

        ProductVariant::withTrashed()->whereKey($variant->id)->update(['expiry_date' => $earliest]);

        $variant->setAttribute('expiry_date', $earliest);
        $variant->syncOriginalAttribute('expiry_date');
    }

    /**
     * Formulir varian menulis `stock` dan `expiry_date` langsung.
     *
     * Tanggal yang diubah di formulir adalah tanggal yang ditampilkannya —
     * tanggal batch bersisa paling awal — jadi batch itulah yang diberi tanggal
     * baru. Selisih stoknya ditutup {@see self::reconcile()}.
     */
    public function syncAfterDirectEdit(ProductVariant $variant, bool $expiryChanged): void
    {
        DB::transaction(function () use ($variant, $expiryChanged) {
            if ($expiryChanged) {
                $shown = $this->remainingBatches($variant, lock: true)->first();

                $shown?->update(['expiry_date' => $variant->expiry_date?->toDateString()]);
            }

            $this->reconcile($variant);
            $this->refreshExpiry($variant);
        });
    }

    /**
     * Varian ini PERNAH bertanggal kedaluwarsa, pada batch mana pun.
     *
     * Dipakai formulir restock untuk menuntut tanggal lagi. Batch tanpa tanggal
     * dijual paling akhir dan tidak pernah terhitung basi, jadi satu kiriman
     * croissant yang lupa diberi tanggal lolos dari seluruh penjaga barang basi
     * tanpa satu pun tanda. Kopi kiloan dan gelas plastik tidak pernah
     * bertanggal, dan tidak pernah dipaksa.
     *
     * `expiry_date` varian ikut dihitung karena ia tetap memegang tanggal
     * terakhir walau seluruh batchnya sudah habis. Kueri kembarannya, untuk
     * satu halaman daftar stok sekaligus, ada di `StockController::paginateVariants()`.
     */
    public function tracksExpiry(ProductVariant $variant): bool
    {
        return $variant->expiry_date !== null
            || ProductStockBatch::where('product_variant_id', $variant->id)->whereNotNull('expiry_date')->exists();
    }

    /**
     * Unit yang masih boleh dijual tanpa konfirmasi.
     */
    public function freshUnits(ProductVariant $variant, ?string $asOf = null): int
    {
        $today = $asOf ?? BusinessClock::today();

        return (int) $this->remainingBatches($variant)
            ->reject(fn (ProductStockBatch $batch) => $batch->isExpiredOn($today))
            ->sum('qty_remaining');
    }

    /**
     * Unit basi per varian, satu kueri untuk seluruh katalog.
     *
     * @param  list<int>  $variantIds
     * @return Collection<int, int> product_variant_id => unit basi
     */
    public function expiredUnitsFor(array $variantIds, ?string $asOf = null): Collection
    {
        if ($variantIds === []) {
            return collect();
        }

        return ProductStockBatch::query()
            ->whereIn('product_variant_id', $variantIds)
            ->expiredOn($asOf ?? BusinessClock::today())
            ->groupBy('product_variant_id')
            ->selectRaw('product_variant_id, SUM(qty_remaining) as units')
            ->pluck('units', 'product_variant_id')
            ->map(fn ($units) => (int) $units);
    }

    /**
     * Kalimat penolakan yang dibaca kasir.
     */
    public function expiredRefusal(ProductVariant $variant, int $freshQty, int $wanted): string
    {
        return "{$variant->name} sudah kedaluwarsa. Yang belum kedaluwarsa tinggal {$freshQty}, diminta {$wanted}. Isi alasan untuk tetap menjual.";
    }

    public function tenantIdOf(ProductVariant $variant): int
    {
        // Tanpa global scope: produknya boleh sudah dihapus (penjualan offline
        // dan void masih menyentuhnya), dan pemanggil tanpa sesi tidak punya
        // tenant untuk dipakai TenantScope.
        return (int) Product::withoutGlobalScopes()->whereKey($variant->product_id)->value('tenant_id');
    }

    /**
     * Batch bersisa, urut: yang punya tanggal paling awal dulu, yang tanpa
     * tanggal paling akhir.
     *
     * @return EloquentCollection<int, ProductStockBatch>
     */
    private function remainingBatches(ProductVariant $variant, bool $lock = false): EloquentCollection
    {
        $query = ProductStockBatch::where('product_variant_id', $variant->id)
            ->remaining()
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('received_at')
            ->orderBy('id');

        return $lock ? $query->lockForUpdate()->get() : $query->get();
    }

    private function currentExpiryOf(ProductVariant $variant): ?string
    {
        $value = ProductVariant::withTrashed()->whereKey($variant->id)->value('expiry_date');

        return $value === null ? null : substr((string) $value, 0, 10);
    }

    private function link(?StockMovement $movement, ProductStockBatch $batch, int $qty): void
    {
        if ($movement === null || $qty === 0) {
            return;
        }

        DB::table('stock_movement_batches')->insert([
            'stock_movement_id' => $movement->id,
            'product_stock_batch_id' => $batch->id,
            'qty' => $qty,
        ]);
    }
}

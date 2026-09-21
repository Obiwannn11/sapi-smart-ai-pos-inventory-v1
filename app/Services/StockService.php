<?php

namespace App\Services;

use App\Models\ProductStockBatch;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu yang mengubah stok.
 *
 * Setiap mutasi di sini menggerakkan DUA hal sekaligus: angka `stock` di varian
 * dan batch di bawahnya ([BL-111]). Urutannya selalu sama — samakan batch
 * dengan stok, ubah stok, gerakkan batch, samakan lagi, tulis ulang tanggal
 * kedaluwarsa turunannya. Aturan pengambilan batchnya sendiri ada di
 * {@see StockBatchService}.
 */
class StockService
{
    public function __construct(private StockBatchService $batches) {}

    /**
     * Kurangi stok saat checkout (dipanggil oleh TransactionService).
     *
     * Barang baik diambil lebih dulu. Kalau yang baik tidak cukup dan sisanya
     * sudah kedaluwarsa, penjualannya DITOLAK kecuali `$allowExpired` — tanda
     * bahwa seseorang sudah mengonfirmasi dan menuliskan alasannya ([BL-108]).
     *
     * @return array{expired_qty: int, earliest_expired: string|null}
     *
     * @throws \Exception stok kurang, atau barang basi tanpa konfirmasi
     */
    public function deduct(ProductVariant $variant, int $qty, int $transactionId, bool $allowExpired = false): array
    {
        return DB::transaction(function () use ($variant, $qty, $transactionId, $allowExpired) {
            $this->batches->reconcile($variant);

            // Atomic decrement with negative stock prevention
            $affected = ProductVariant::where('id', $variant->id)
                ->where('stock', '>=', $qty)
                ->update(['stock' => DB::raw("stock - {$qty}")]);

            if (! $affected) {
                $variant->refresh();
                throw new \Exception(
                    "Stok {$variant->name} tidak cukup. Tersedia: {$variant->stock}, diminta: {$qty}"
                );
            }

            $movement = StockMovement::create([
                'tenant_id' => $this->batches->tenantIdOf($variant),
                'product_variant_id' => $variant->id,
                'type' => StockMovement::TYPE_SALE,
                'qty' => -$qty,
                'notes' => "Penjualan dari transaksi #{$transactionId}",
                'reference_id' => $transactionId,
            ]);

            $taken = $this->batches->take($variant, $qty, $movement, allowExpired: $allowExpired);

            $this->batches->refreshExpiry($variant);

            return [
                'expired_qty' => $taken['expired_qty'],
                'earliest_expired' => $taken['earliest_expired'],
            ];
        });
    }

    /**
     * Kurangi stok untuk penjualan offline yang SUDAH terjadi.
     *
     * Kebalikan dari {@see self::deduct()}: tidak pernah menolak. Stok boleh
     * minus dan barang basi boleh ikut terambil, karena barangnya sudah
     * keluar dari rak — yang bisa dilakukan hanyalah mencatatnya dengan jujur.
     * Pemanggil yang memutuskan apakah hasilnya perlu ditinjau owner.
     *
     * Pemanggil wajib sudah memegang kunci baris varian.
     *
     * @param  string  $asOf  hari toko saat penjualan TERJADI
     * @return array{expired_qty: int, earliest_expired: string|null, went_negative: bool}
     */
    public function deductOffline(ProductVariant $variant, int $qty, int $transactionId, int $tenantId, string $asOf): array
    {
        $this->batches->reconcile($variant);

        // Jangan pakai decrement() lalu baca $variant->stock: atribut in-memory
        // diturunkan dari nilai yang model tahu sebelumnya, bukan hasil baca
        // ulang DB, sehingga cek minus bisa meleset. Baris sudah di-lock, jadi
        // hitung eksplisit dari nilai ter-lock.
        $newStock = $variant->stock - $qty;
        $variant->update(['stock' => $newStock]);

        $movement = StockMovement::create([
            'tenant_id' => $tenantId,
            'product_variant_id' => $variant->id,
            'type' => StockMovement::TYPE_SALE,
            'qty' => -$qty,
            'notes' => "Penjualan offline (sync) #{$transactionId}",
            'reference_id' => $transactionId,
        ]);

        $taken = $this->batches->take($variant, $qty, $movement, allowExpired: true, asOf: $asOf);

        $this->batches->refreshExpiry($variant);

        return [
            'expired_qty' => $taken['expired_qty'],
            'earliest_expired' => $taken['earliest_expired'],
            'went_negative' => $newStock < 0,
        ];
    }

    /**
     * Kembalikan stok saat void transaksi (dipanggil oleh TransactionService).
     *
     * Unitnya kembali ke batch yang dulu melepasnya — termasuk ke batch basi,
     * kalau memang dari sana ia diambil.
     */
    public function restore(ProductVariant $variant, int $qty, int $transactionId): void
    {
        DB::transaction(function () use ($variant, $qty, $transactionId) {
            $this->batches->reconcile($variant);

            $variant->increment('stock', $qty);

            $movement = StockMovement::create([
                'tenant_id' => $this->batches->tenantIdOf($variant),
                'product_variant_id' => $variant->id,
                'type' => StockMovement::TYPE_VOID,
                'qty' => $qty,
                'notes' => "Void transaksi #{$transactionId} — stok dikembalikan",
                'reference_id' => $transactionId,
            ]);

            $this->batches->giveBack($variant, $qty, $transactionId, $movement);
            $this->batches->reconcile($variant);
            $this->batches->refreshExpiry($variant);
        });
    }

    /**
     * Koreksi stok karena transaksi yang sudah selesai diedit.
     *
     * Qty naik → barang tambahan diambil dari yang BAIK saja. Layar edit tidak
     * punya tempat untuk konfirmasi barang basi, dan penjualan barang basi
     * tidak boleh punya jalur yang tidak menanyakannya.
     *
     * @throws \Exception stok kurang, atau yang tersisa hanya barang basi
     */
    public function applyEditDelta(ProductVariant $variant, int $delta, int $transactionId, int $tenantId): void
    {
        if ($delta === 0) {
            return;
        }

        if ($delta > 0) {
            $this->batches->reconcile($variant);

            if ($variant->trashed() || $variant->stock < $delta) {
                throw new \Exception("Stok {$variant->name} tidak cukup untuk edit. Tersedia: {$variant->stock}, butuh tambahan: {$delta}");
            }

            $fresh = $this->batches->freshUnits($variant);

            if ($fresh < $delta) {
                throw new \Exception("Stok {$variant->name} yang belum kedaluwarsa tinggal {$fresh}, butuh tambahan {$delta}. Barang kedaluwarsa hanya bisa dijual dari layar kasir dengan alasan.");
            }

            $variant->decrement('stock', $delta);
        } elseif (! $variant->trashed()) {
            // Varian yang sudah dihapus: stok fisiknya tidak dikembalikan, tapi
            // mutasinya tetap dicatat — perilaku sebelum buku batch ada.
            $this->batches->reconcile($variant);
            $variant->increment('stock', abs($delta));
        }

        $movement = StockMovement::create([
            'tenant_id' => $tenantId,
            'product_variant_id' => $variant->id,
            'type' => StockMovement::TYPE_EDIT,
            'qty' => -$delta, // penjualan naik → stok turun (qty negatif)
            'notes' => "Edit transaksi #{$transactionId}",
            'reference_id' => $transactionId,
        ]);

        if ($variant->trashed()) {
            return;
        }

        if ($delta > 0) {
            $this->batches->take($variant, $delta, $movement);
        } else {
            $this->batches->giveBack($variant, abs($delta), $transactionId, $movement);
        }

        $this->batches->reconcile($variant);
        $this->batches->refreshExpiry($variant);
    }

    /**
     * Unit yang boleh dijual tanpa konfirmasi barang basi.
     */
    public function freshUnits(ProductVariant $variant): int
    {
        return $this->batches->freshUnits($variant);
    }

    /**
     * Restock — tambah stok karena terima barang.
     *
     * Tanggal kedaluwarsanya jadi milik batch BARU. Batch lama tetap memegang
     * tanggalnya sendiri; sebelum [BL-111] tanggal itu ditimpa tanpa jejak.
     */
    public function restock(ProductVariant $variant, int $qty, ?string $notes = null, ?string $expiryDate = null): void
    {
        DB::transaction(function () use ($variant, $qty, $notes, $expiryDate) {
            $this->batches->reconcile($variant);

            $variant->increment('stock', $qty);

            $movement = StockMovement::create([
                'tenant_id' => $this->batches->tenantIdOf($variant),
                'product_variant_id' => $variant->id,
                'type' => StockMovement::TYPE_RESTOCK,
                'qty' => $qty,
                'notes' => $notes ?? 'Restock',
            ]);

            $this->batches->receive($variant, $qty, $expiryDate, ProductStockBatch::SOURCE_RESTOCK, $movement);

            // Stok yang minus sebelum restock (penjualan offline mendahului
            // catatannya) menelan sebagian batch baru di sini.
            $this->batches->reconcile($variant);
            $this->batches->refreshExpiry($variant);
        });
    }

    /**
     * Adjustment — koreksi stok manual (bisa positif atau negatif).
     *
     * Tanpa batch sasaran, koreksi turun mengambil barang BASI lebih dulu
     * (alasan paling umum stok dikurangi manual adalah membuang barang yang
     * sudah lewat tanggal), dan koreksi naik menumpang batch yang terakhir
     * datang. Pemilik yang tahu barang mana yang bergerak memilih batchnya
     * sendiri, atau — untuk koreksi naik — membuat batch baru bertanggal.
     *
     * @param  int|null  $batchId  batch varian ini yang dikurangi atau ditambah
     * @param  bool  $newBatch  koreksi naik jadi batch baru, dengan `$expiryDate`
     */
    public function adjust(
        ProductVariant $variant,
        int $qty,
        ?string $notes = null,
        ?int $batchId = null,
        bool $newBatch = false,
        ?string $expiryDate = null,
    ): void {
        DB::transaction(function () use ($variant, $qty, $notes, $batchId, $newBatch, $expiryDate) {
            // Lock row to prevent race condition
            $variant = ProductVariant::lockForUpdate()->find($variant->id);

            if ($qty < 0 && $variant->stock < abs($qty)) {
                throw new \Exception(
                    "Adjustment gagal: stok {$variant->name} akan menjadi negatif. Tersedia: {$variant->stock}"
                );
            }

            $this->batches->reconcile($variant);

            if ($qty > 0) {
                $variant->increment('stock', $qty);
            } else {
                $variant->decrement('stock', abs($qty));
            }

            $movement = StockMovement::create([
                'tenant_id' => $this->batches->tenantIdOf($variant),
                'product_variant_id' => $variant->id,
                'type' => StockMovement::TYPE_ADJUSTMENT,
                'qty' => $qty,
                'notes' => $notes ?? 'Adjustment manual',
            ]);

            if ($qty > 0 && $newBatch) {
                $this->batches->receive($variant, $qty, $expiryDate, ProductStockBatch::SOURCE_ADJUSTMENT, $movement);
            } elseif ($qty > 0 && $batchId !== null) {
                $this->batches->putInto($variant, $batchId, $qty, $movement);
            } elseif ($qty > 0) {
                $this->batches->putBack($variant, $qty, $movement);
            } elseif ($batchId !== null) {
                $this->batches->takeFromBatch($variant, $batchId, abs($qty), $movement);
            } else {
                $this->batches->take($variant, abs($qty), $movement, allowExpired: true, expiredFirst: true);
            }

            $this->batches->reconcile($variant);
            $this->batches->refreshExpiry($variant);
        });
    }
}

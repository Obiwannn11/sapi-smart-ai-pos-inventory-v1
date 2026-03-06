<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Kurangi stok saat checkout (dipanggil oleh TransactionService).
     * HARUS dipanggil di dalam DB::transaction().
     */
    public function deduct(ProductVariant $variant, int $qty, int $transactionId): void
    {
        // Atomic decrement with negative stock prevention
        $affected = ProductVariant::where('id', $variant->id)
            ->where('stock', '>=', $qty)
            ->update(['stock' => DB::raw("stock - {$qty}")]);

        if (!$affected) {
            $variant->refresh();
            throw new \Exception(
                "Stok {$variant->name} tidak cukup. Tersedia: {$variant->stock}, diminta: {$qty}"
            );
        }

        StockMovement::create([
            'tenant_id' => $variant->product->tenant_id,
            'product_variant_id' => $variant->id,
            'type' => StockMovement::TYPE_SALE,
            'qty' => -$qty,
            'notes' => "Penjualan dari transaksi #{$transactionId}",
            'reference_id' => $transactionId,
        ]);
    }

    /**
     * Kembalikan stok saat void transaksi (dipanggil oleh TransactionService).
     * HARUS dipanggil di dalam DB::transaction().
     */
    public function restore(ProductVariant $variant, int $qty, int $transactionId): void
    {
        $variant->increment('stock', $qty);

        StockMovement::create([
            'tenant_id' => $variant->product->tenant_id,
            'product_variant_id' => $variant->id,
            'type' => StockMovement::TYPE_VOID,
            'qty' => $qty,
            'notes' => "Void transaksi #{$transactionId} — stok dikembalikan",
            'reference_id' => $transactionId,
        ]);
    }

    /**
     * Restock — tambah stok karena terima barang.
     */
    public function restock(ProductVariant $variant, int $qty, ?string $notes = null, ?string $expiryDate = null): void
    {
        DB::transaction(function () use ($variant, $qty, $notes, $expiryDate) {
            $variant->increment('stock', $qty);

            // Update expiry_date jika diisi (tanggal expiry batch terakhir)
            if ($expiryDate) {
                $variant->update(['expiry_date' => $expiryDate]);
            }

            StockMovement::create([
                'tenant_id' => $variant->product->tenant_id,
                'product_variant_id' => $variant->id,
                'type' => StockMovement::TYPE_RESTOCK,
                'qty' => $qty,
                'notes' => $notes ?? 'Restock',
            ]);
        });
    }

    /**
     * Adjustment — koreksi stok manual (bisa positif atau negatif).
     */
    public function adjust(ProductVariant $variant, int $qty, ?string $notes = null): void
    {
        DB::transaction(function () use ($variant, $qty, $notes) {
            // Lock row to prevent race condition
            $variant = ProductVariant::lockForUpdate()->find($variant->id);

            if ($qty < 0 && $variant->stock < abs($qty)) {
                throw new \Exception(
                    "Adjustment gagal: stok {$variant->name} akan menjadi negatif. Tersedia: {$variant->stock}"
                );
            }

            if ($qty > 0) {
                $variant->increment('stock', $qty);
            } else {
                $variant->decrement('stock', abs($qty));
            }

            StockMovement::create([
                'tenant_id' => $variant->product->tenant_id,
                'product_variant_id' => $variant->id,
                'type' => StockMovement::TYPE_ADJUSTMENT,
                'qty' => $qty,
                'notes' => $notes ?? 'Adjustment manual',
            ]);
        });
    }
}

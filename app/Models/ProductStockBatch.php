<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kedatangan barang dengan tanggal kedaluwarsanya sendiri ([BL-111]).
 *
 * Sengaja TIDAK memakai `BelongsToTenant`. Baris ini ditulis dari tiga konteks
 * yang berbeda sesinya — kasir yang login, sinkronisasi offline yang menerima
 * kasirnya sebagai argumen, dan pencatat harian tanpa sesi sama sekali — dan
 * `TenantScope` hanya hidup di yang pertama. Setiap pembacanya menyaring lewat
 * varian atau `tenant_id` secara eksplisit.
 */
class ProductStockBatch extends Model
{
    /** @use HasFactory<\Database\Factories\ProductStockBatchFactory> */
    use HasFactory;

    public const SOURCE_OPENING = 'opening';

    public const SOURCE_RESTOCK = 'restock';

    public const SOURCE_ADJUSTMENT = 'adjustment';

    public const SOURCE_RECONCILE = 'reconcile';

    protected $fillable = [
        'tenant_id', 'product_variant_id', 'expiry_date',
        'qty_received', 'qty_remaining', 'received_at', 'source',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'received_at' => 'datetime',
            'qty_received' => 'integer',
            'qty_remaining' => 'integer',
        ];
    }

    /**
     * Sudah lewat tanggalnya pada hari toko `$today`.
     *
     * Tanggal X sendiri masih sah dijual — batasnya sama dengan
     * `DiscountService::isDiscountable()` dan `ExpiredStockRecorder`.
     */
    public function isExpiredOn(string $today): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->toDateString() < $today;
    }

    /**
     * @param  Builder<ProductStockBatch>  $query
     */
    public function scopeRemaining(Builder $query): void
    {
        $query->where('product_stock_batches.qty_remaining', '>', 0);
    }

    /**
     * Batch bersisa yang sudah lewat tanggalnya pada hari toko `$today`.
     *
     * @param  Builder<ProductStockBatch>  $query
     */
    public function scopeExpiredOn(Builder $query, string $today): void
    {
        $query->remaining()
            ->whereNotNull('product_stock_batches.expiry_date')
            ->whereDate('product_stock_batches.expiry_date', '<', $today);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}

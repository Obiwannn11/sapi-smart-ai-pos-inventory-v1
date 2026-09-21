<?php

namespace App\Models;

use App\Services\StockBatchService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id', 'name', 'sku', 'price', 'cost_price', 'stock', 'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }

    /**
     * Stok awal varian baru jadi batch pertamanya ([BL-111]).
     *
     * Hook, bukan tugas pemanggil: varian lahir dari formulir produk, formulir
     * varian, factory, dan seeder — dan setiap tempat yang lupa melahirkan
     * batch akan membuat stoknya tak bertanggal sampai ada yang merekonsiliasi.
     */
    protected static function booted(): void
    {
        static::created(function (ProductVariant $variant) {
            app(StockBatchService::class)->open($variant);
        });
    }

    // --- Relationships ---
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Kedatangan barang varian ini, masing-masing dengan tanggal kedaluwarsanya
     * sendiri ([BL-111]).
     */
    public function stockBatches(): HasMany
    {
        return $this->hasMany(ProductStockBatch::class);
    }

    /**
     * Tiap kali varian ini melewati tanggal kedaluwarsanya dengan stok tersisa.
     *
     * Jamak, bukan tunggal: varian yang direstok mendapat tanggal kedaluwarsa
     * baru, dan tiap tanggal yang lewat meninggalkan barisnya sendiri
     * ([BL-105]).
     */
    public function expiredStockRecords(): HasMany
    {
        return $this->hasMany(ExpiredStockRecord::class);
    }
}

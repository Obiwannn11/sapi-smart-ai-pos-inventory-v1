<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StockMovement extends Model
{
    use BelongsToTenant, HasFactory;

    const UPDATED_AT = null;

    // Type constants
    const TYPE_SALE = 'sale';

    const TYPE_RESTOCK = 'restock';

    const TYPE_ADJUSTMENT = 'adjustment';

    const TYPE_VOID = 'void';

    const TYPE_EDIT = 'edit';

    protected $fillable = [
        'tenant_id', 'product_variant_id', 'type', 'qty', 'notes', 'reference_id',
    ];

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'reference_id');
    }

    /**
     * Batch yang disentuh mutasi ini ([BL-111]). `pivot->qty` bertanda, searah
     * dengan `qty` mutasinya: negatif diambil dari batch itu, positif masuk ke
     * batch itu.
     */
    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(ProductStockBatch::class, 'stock_movement_batches', 'stock_movement_id', 'product_stock_batch_id')
            ->withPivot('qty');
    }
}

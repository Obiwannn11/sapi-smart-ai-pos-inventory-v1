<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToTenant;

    const UPDATED_AT = null;

    // Type constants
    const TYPE_SALE = 'sale';
    const TYPE_RESTOCK = 'restock';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_VOID = 'void';

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
}

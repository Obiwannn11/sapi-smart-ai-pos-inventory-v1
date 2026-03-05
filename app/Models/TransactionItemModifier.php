<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItemModifier extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transaction_item_id', 'modifier_id', 'modifier_name', 'extra_price',
    ];

    protected function casts(): array
    {
        return [
            'extra_price' => 'decimal:2',
        ];
    }

    // --- Relationships ---
    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class);
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transaction_id', 'product_variant_id',
        'variant_name', 'qty', 'unit_price', 'subtotal', 'notes',
        // Jejak potongan ([BL-018]). `unit_price` tetap berarti "yang
        // benar-benar dibayar"; yang di bawah ini konteks yang membuatnya bisa
        // dipertanggungjawabkan berbulan-bulan kemudian.
        'original_unit_price', 'discount_amount', 'discount_rule_id', 'discount_reason',
        'cost_price_at_sale', 'margin_floor_at_sale', 'below_floor_approved_by',
        // Jejak penjualan barang yang sudah kedaluwarsa ([BL-108]).
        'expired_qty', 'expiry_date_at_sale', 'expired_sale_confirmed_by', 'expired_sale_reason',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'original_unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'cost_price_at_sale' => 'decimal:2',
            'margin_floor_at_sale' => 'decimal:2',
            'expired_qty' => 'integer',
            'expiry_date_at_sale' => 'date',
        ];
    }

    /** Sebagian unit baris ini diambil dari batch yang sudah kedaluwarsa. */
    public function soldExpired(): bool
    {
        return (int) $this->expired_qty > 0;
    }

    /** Baris ini dijual di bawah lantai margin, disetujui seseorang. */
    public function isBelowFloor(): bool
    {
        return $this->below_floor_approved_by !== null;
    }

    /** Ada potongan apa pun pada baris ini. */
    public function isDiscounted(): bool
    {
        return (float) $this->discount_amount > 0;
    }

    // --- Relationships ---
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(TransactionItemModifier::class);
    }

    public function discountRule(): BelongsTo
    {
        return $this->belongsTo(DiscountRule::class);
    }

    public function belowFloorApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'below_floor_approved_by');
    }

    public function expiredSaleConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expired_sale_confirmed_by');
    }
}

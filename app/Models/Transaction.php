<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory, BelongsToTenant;

    // --- Payment Status Constants ---
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_VOIDED = 'voided';

    // --- Source Constants ---
    const SOURCE_POS = 'pos';
    const SOURCE_SELF_ORDER = 'self_order';

    // --- Order Type Constants ---
    const ORDER_TYPE_DINE_IN = 'dine_in';
    const ORDER_TYPE_PICKUP = 'pickup';

    // --- Fulfillment Status Constants ---
    const FULFILLMENT_WAITING = 'waiting';
    const FULFILLMENT_PREPARING = 'preparing';
    const FULFILLMENT_READY = 'ready';
    const FULFILLMENT_DONE = 'done';

    protected $fillable = [
        'tenant_id', 'user_id', 'code', 'status',
        'total_amount', 'change_amount', 'notes',
        'source', 'order_type', 'fulfillment_status',
        'customer_name', 'table_number',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
        ];
    }

    // --- Helper Methods ---

    /**
     * Apakah transaksi ini dari self-order channel?
     */
    public function isSelfOrder(): bool
    {
        return $this->source === self::SOURCE_SELF_ORDER;
    }

    /**
     * Apakah fulfillment tracking aktif untuk transaksi ini?
     */
    public function hasFulfillmentTracking(): bool
    {
        return $this->fulfillment_status !== null;
    }

    /**
     * Advance fulfillment ke status berikutnya.
     * waiting → preparing → ready → done
     */
    public function advanceFulfillment(): self
    {
        $flow = [
            self::FULFILLMENT_WAITING => self::FULFILLMENT_PREPARING,
            self::FULFILLMENT_PREPARING => self::FULFILLMENT_READY,
            self::FULFILLMENT_READY => self::FULFILLMENT_DONE,
        ];

        $next = $flow[$this->fulfillment_status] ?? null;

        if ($next) {
            $this->update(['fulfillment_status' => $next]);
        }

        return $this;
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class);
    }
}

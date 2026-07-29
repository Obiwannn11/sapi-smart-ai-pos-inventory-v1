<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use BelongsToTenant, HasFactory;

    // --- Payment Status Constants ---
    const STATUS_PENDING = 'pending';

    const STATUS_COMPLETED = 'completed';

    const STATUS_VOIDED = 'voided';

    // --- Source Constants ---
    const SOURCE_POS = 'pos';

    const SOURCE_SELF_ORDER = 'self_order';

    // --- Channel Constants ---
    // Orthogonal to source: source says where the order came from, channel says
    // whether it reached us live or was captured offline and synced later.
    const CHANNEL_ONLINE = 'online';

    const CHANNEL_OFFLINE = 'offline';

    // --- Sync Status Constants ---
    // null = healthy. NEEDS_REVIEW = synced, but something drifted while the
    // device was offline (negative stock, price change, deleted variant) and an
    // owner must reconcile it. The sale itself is never rejected.
    const SYNC_NEEDS_REVIEW = 'needs_review';

    // --- Order Type Constants ---
    const ORDER_TYPE_DINE_IN = 'dine_in';

    const ORDER_TYPE_PICKUP = 'pickup';

    // --- Fulfillment Status Constants ---
    const FULFILLMENT_WAITING = 'waiting';

    const FULFILLMENT_PREPARING = 'preparing';

    const FULFILLMENT_READY = 'ready';

    const FULFILLMENT_DONE = 'done';

    protected $fillable = [
        'tenant_id', 'user_id', 'code', 'client_uuid', 'status',
        'total_amount', 'change_amount', 'notes',
        'source', 'order_type', 'fulfillment_status',
        'queue_number', 'sort_index', 'preparing_at', 'ready_at',
        'customer_name', 'table_number',
        'edited_at', 'edited_by',
        'channel', 'occurred_at', 'synced_at', 'sync_status', 'device_id',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'edited_at' => 'datetime',
            'occurred_at' => 'datetime',
            'synced_at' => 'datetime',
            'preparing_at' => 'datetime',
            'ready_at' => 'datetime',
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
     * Apakah transaksi ini ditangkap saat offline lalu disinkronkan?
     */
    public function isOffline(): bool
    {
        return $this->channel === self::CHANNEL_OFFLINE;
    }

    /**
     * Apakah transaksi ini punya anomali yang menunggu koreksi owner?
     */
    public function needsReview(): bool
    {
        return $this->sync_status === self::SYNC_NEEDS_REVIEW;
    }

    /**
     * Kapan penjualan ini benar-benar terjadi.
     *
     * Transaksi online: created_at sudah benar. Transaksi offline: created_at
     * adalah waktu sync (bisa berhari-hari setelahnya), jadi occurred_at yang
     * dipakai. Semua laporan wajib lewat sini, bukan created_at mentah.
     */
    public function effectiveDate(): \Illuminate\Support\Carbon
    {
        return $this->occurred_at ?? $this->created_at;
    }

    /**
     * Ekspresi SQL untuk tanggal efektif penjualan.
     *
     * Padanan query dari effectiveDate(): occurred_at bila ada, selain itu
     * created_at. COALESCE didukung MySQL maupun SQLite (test suite).
     *
     * Pakai ini di SELECT/GROUP BY mentah; untuk filter pakai scope
     * whereEffectiveDate()/whereEffectiveBetween().
     */
    public static function effectiveDateSql(): string
    {
        return 'COALESCE(transactions.occurred_at, transactions.created_at)';
    }

    /**
     * Filter transaksi pada satu tanggal kalender penjualan sebenarnya.
     */
    public function scopeWhereEffectiveDate(Builder $query, mixed $date): Builder
    {
        return $query->whereRaw('DATE('.self::effectiveDateSql().') = ?', [
            $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date,
        ]);
    }

    /**
     * Filter transaksi dalam rentang tanggal penjualan sebenarnya (inklusif).
     */
    public function scopeWhereEffectiveBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query->whereRaw(self::effectiveDateSql().' between ? and ?', [$from, $to]);
    }

    /**
     * Filter transaksi sejak tanggal tertentu (inklusif).
     */
    public function scopeWhereEffectiveFrom(Builder $query, mixed $from): Builder
    {
        return $query->whereRaw('DATE('.self::effectiveDateSql().') >= ?', [
            $from instanceof \DateTimeInterface ? $from->format('Y-m-d') : $from,
        ]);
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

    public function edits(): HasMany
    {
        return $this->hasMany(TransactionEdit::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}

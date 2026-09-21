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

    /**
     * Tagihan terbuka yang lewat umurnya: berhenti jadi tagihan hidup, dicatat
     * sebagai kas negatif ([BL-031]). Bukan `voided` — barangnya sudah dibawa
     * pelanggan, jadi stok TIDAK dipulihkan di jalur ini. Bukan `completed` —
     * uangnya tidak pernah masuk laci mana pun.
     */
    const STATUS_UNSETTLED = 'unsettled';

    /**
     * Berapa lama sebuah tagihan terbuka hidup, dihitung dari tanggal EFEKTIF
     * penjualannya. Dipakai bersama oleh sapuan terjadwal dan daftar tagihan
     * di topbar kasir — dua angka yang berbeda berarti kasir melihat tagihan
     * yang sebenarnya sudah mati, atau sebaliknya.
     */
    const OPEN_BILL_LIFETIME_HOURS = 24;

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
        'tenant_id', 'user_id', 'cash_drawer_id', 'code', 'client_uuid', 'status',
        'subtotal_amount', 'tax_amount', 'total_amount', 'change_amount', 'notes',
        'tax_rate', 'tax_mode', 'tax_label',
        'service_charge_amount', 'service_charge_rate', 'service_charge_label',
        'source', 'order_type', 'fulfillment_status',
        'queue_number', 'sort_index', 'preparing_at', 'ready_at',
        'customer_name', 'table_number',
        'edited_at', 'edited_by',
        'channel', 'occurred_at', 'synced_at', 'sync_status', 'device_id',
        'unsettled_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'service_charge_amount' => 'decimal:2',
            'service_charge_rate' => 'decimal:2',
            'edited_at' => 'datetime',
            'occurred_at' => 'datetime',
            'unsettled_at' => 'datetime',
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
     * Apakah tagihan ini sudah lewat umurnya dan jadi kas negatif ([BL-031])?
     */
    public function isUnsettled(): bool
    {
        return $this->status === self::STATUS_UNSETTLED;
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
     * Batas umur tagihan terbuka: sebelum cap waktu ini, sebuah tagihan sudah
     * mati ([BL-031]).
     *
     * Satu tempat untuk seluruh aplikasi. Aturan 24 jam yang ditulis ulang di
     * tiap pemanggil adalah aturan yang suatu hari akan berbeda di salah
     * satunya, dan bedanya baru terlihat sebagai tagihan hantu di topbar
     * kasir.
     */
    public static function openBillCutoff(mixed $now = null): \Illuminate\Support\Carbon
    {
        $reference = $now instanceof \DateTimeInterface
            ? \Illuminate\Support\Carbon::instance($now)
            : \Illuminate\Support\Carbon::now();

        return $reference->copy()->subHours(self::OPEN_BILL_LIFETIME_HOURS);
    }

    /**
     * Tagihan terbuka POS yang MASIH hidup.
     *
     * `source = pos` bukan kehati-hatian berlebihan: self-order yang `pending`
     * juga ada, tapi stoknya belum dikurangi dan ia punya jalur kedaluwarsanya
     * sendiri (`voidExpiredSelfOrder`). Menyapunya lewat sini akan mencatat
     * kas negatif atas barang yang tidak pernah keluar.
     */
    public function scopeLiveOpenBills(Builder $query, mixed $now = null): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('source', self::SOURCE_POS)
            ->whereRaw(self::effectiveDateSql().' > ?', [self::openBillCutoff($now)]);
    }

    /**
     * Tagihan terbuka POS yang sudah lewat umurnya dan belum dipindahkan ke
     * kas negatif. Inilah yang disapu `open-bills:expire`.
     */
    public function scopeExpiredOpenBills(Builder $query, mixed $now = null): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('source', self::SOURCE_POS)
            ->whereRaw(self::effectiveDateSql().' <= ?', [self::openBillCutoff($now)]);
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

    /**
     * Laci yang menerima uang penjualan ini ([BL-028] Tahap B langkah 1).
     *
     * `null` pada baris lama berarti "lahir sebelum kolomnya ada", bukan
     * "tidak jatuh ke laci mana pun" — keduanya tidak bisa dibedakan dari
     * nilainya. Lihat docblock migrasi `add_cash_drawer_id_to_transactions_table`.
     */
    public function cashDrawer(): BelongsTo
    {
        return $this->belongsTo(CashDrawer::class);
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

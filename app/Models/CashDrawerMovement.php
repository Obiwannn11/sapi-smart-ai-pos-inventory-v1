<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali uang keluar dari laci atau masuk ke laci di luar penjualan
 * ([BL-087]).
 *
 * **Yang ditahan efeknya, bukan pencatatannya.** Kasir selalu boleh mencatat —
 * itu keputusan pemilik 2026-08-21, dan syaratnya masuk akal: catatan kas yang
 * bisa ditolak di tengah jalan adalah catatan kas yang tidak pernah terisi.
 * Yang menunggu persetujuan hanyalah apakah angkanya boleh menggerakkan
 * `expected_amount`.
 *
 * Tanpa penahan itu, fitur ini akan membatalkan `[BL-086]` dari sisi
 * sebaliknya: kasir yang lacinya kurang Rp 50.000 tinggal mencatat pengeluaran
 * Rp 50.000, dan selisihnya jadi nol. Pengeluaran adalah SATU-SATUNYA angka
 * kas yang sumbernya ucapan manusia — semua angka lain punya pembanding di
 * `transactions`.
 */
class CashDrawerMovement extends Model
{
    use BelongsToTenant, HasFactory;

    /** Uang keluar dari laci — setoran ke pemilik, beli galon, tukar receh. */
    public const TYPE_PAYOUT = 'payout';

    /** Uang masuk ke laci di luar penjualan — tambahan uang kecil. */
    public const TYPE_DEPOSIT = 'deposit';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id', 'cash_drawer_id', 'user_id',
        'type', 'amount', 'reason', 'proof_path', 'status', 'reviewed_by', 'reviewed_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    // --- Helpers ---

    /**
     * Disetujui otomatis karena di bawah ambang tenant.
     *
     * Dibedakan dari persetujuan pemilik lewat `reviewed_by` yang kosong, bukan
     * lewat kolom sendiri: keduanya sama-sama `approved` dan sama-sama
     * menggerakkan angka, dan yang membedakannya hanya apakah ada manusia yang
     * membacanya. Pemilik yang menelusuri selisih perlu bisa memisahkan itu.
     */
    public function wasAutoApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED && $this->reviewed_by === null;
    }

    /** Tanda arah: uang keluar bernilai negatif terhadap isi laci. */
    public function signedAmount(): float
    {
        return $this->type === self::TYPE_PAYOUT
            ? -(float) $this->amount
            : (float) $this->amount;
    }

    /**
     * Hanya yang disetujui yang boleh menggerakkan `expected_amount`.
     *
     * @param  Builder<CashDrawerMovement>  $query
     * @return Builder<CashDrawerMovement>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * @param  Builder<CashDrawerMovement>  $query
     * @return Builder<CashDrawerMovement>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cashDrawer(): BelongsTo
    {
        return $this->belongsTo(CashDrawer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

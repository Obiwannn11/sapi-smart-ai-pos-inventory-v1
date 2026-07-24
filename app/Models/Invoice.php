<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory;

    /** Terbit, belum ada bukti bayar yang diunggah. */
    public const STATUS_UNPAID = 'unpaid';

    /** Bukti transfer sudah diunggah, menunggu diperiksa pemilik SaaS. */
    public const STATUS_AWAITING_VERIFICATION = 'awaiting_verification';

    /** Bukti diterima. */
    public const STATUS_PAID = 'paid';

    /** Bukti ditolak — palsu, nominal kurang, atau salah unggah. */
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id', 'subscription_id', 'period', 'amount', 'status', 'due_date',
        'proof_path', 'submitted_at', 'paid_at', 'verified_by', 'verified_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'submitted_at' => 'datetime',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Akun platform yang memeriksa bukti bayar — pemilik SaaS, bukan pengguna
     * tenant.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'verified_by');
    }

    // --- Helpers ---
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isAwaitingVerification(): bool
    {
        return $this->status === self::STATUS_AWAITING_VERIFICATION;
    }
}

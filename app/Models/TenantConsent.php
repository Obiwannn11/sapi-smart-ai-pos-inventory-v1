<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantConsent extends Model
{
    /** @use HasFactory<\Database\Factories\TenantConsentFactory> */
    use HasFactory;

    /** Jalur harga normal — tidak ada data bisnis yang dibuka. */
    public const TYPE_NORMAL = 'normal';

    /** Jalur subsidi — omset bulanan dibuka untuk penetapan harga (Tahap C). */
    public const TYPE_SUBSIDIZED = 'subsidized';

    protected $fillable = [
        'tenant_id', 'user_id', 'type', 'version', 'agreed_at', 'ip', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'agreed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Owner tenant yang menyetujui. Bisa null bila akunnya sudah dihapus —
     * buktinya tetap tinggal, hanya penunjuknya yang lepas.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // --- Scopes ---
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    // --- Helpers ---
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}

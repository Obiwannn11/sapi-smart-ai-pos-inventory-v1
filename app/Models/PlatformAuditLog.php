<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan tindakan pemilik SaaS. Append-only — tidak ada jalur ubah/hapus.
 */
class PlatformAuditLog extends Model
{
    /** Tabel hanya punya created_at (lihat migration). */
    public const UPDATED_AT = null;

    protected $fillable = [
        'platform_user_id', 'action', 'subject_type', 'subject_id', 'meta', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * Catat satu tindakan. Dipakai dari controller platform.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function record(string $action, ?Model $subject = null, array $meta = []): self
    {
        return self::create([
            'platform_user_id' => auth('platform')->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => request()->ip(),
        ]);
    }

    // --- Relationships ---
    public function platformUser(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class);
    }
}

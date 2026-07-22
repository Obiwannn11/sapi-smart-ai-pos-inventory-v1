<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan tindakan pemilik SaaS. Append-only — tidak ada jalur ubah/hapus.
 *
 * Dua derajat kejadian; garisnya dijelaskan di config/platform-audit.php.
 */
class PlatformAuditLog extends Model
{
    /** Akses baca yang wajar berulang — dideduplikasi, disimpan lebih singkat. */
    public const SEVERITY_ROUTINE = 'routine';

    /** Perubahan keadaan, data bisnis klien, kejadian keamanan — selalu dicatat. */
    public const SEVERITY_SENSITIVE = 'sensitive';

    /** Tabel hanya punya created_at (lihat migration). */
    public const UPDATED_AT = null;

    protected $fillable = [
        'platform_user_id', 'action', 'severity', 'subject_type', 'subject_id', 'meta', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * Catat kejadian sensitif — selalu menghasilkan baris baru.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function record(string $action, ?Model $subject = null, array $meta = []): self
    {
        return self::write($action, self::SEVERITY_SENSITIVE, $subject, $meta);
    }

    /**
     * Catat kejadian rutin, dideduplikasi.
     *
     * Kejadian rutin yang sama (aktor + aksi + subjek) dalam jendela waktu
     * config('platform-audit.routine_dedupe_minutes') hanya menghasilkan satu
     * baris. Tanpa ini, satu sesi menengok daftar tenant — refresh, pindah
     * halaman, bolak-balik — menghasilkan puluhan baris identik yang
     * menenggelamkan kejadian yang benar-benar perlu terlihat.
     *
     * Sengaja tidak dihapus sama sekali: pertanyaan "siapa membuka daftar klien
     * saya minggu lalu?" tetap harus bisa dijawab.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function recordRoutine(string $action, ?Model $subject = null, array $meta = []): ?self
    {
        $window = now()->subMinutes((int) config('platform-audit.routine_dedupe_minutes'));

        $alreadyLogged = self::query()
            ->where('action', $action)
            ->where('severity', self::SEVERITY_ROUTINE)
            ->where('platform_user_id', auth('platform')->id())
            ->where('subject_type', $subject ? $subject::class : null)
            ->where('subject_id', $subject?->getKey())
            ->where('created_at', '>=', $window)
            ->exists();

        if ($alreadyLogged) {
            return null;
        }

        return self::write($action, self::SEVERITY_ROUTINE, $subject, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function write(string $action, string $severity, ?Model $subject, array $meta): self
    {
        return self::create([
            'platform_user_id' => auth('platform')->id(),
            'action' => $action,
            'severity' => $severity,
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

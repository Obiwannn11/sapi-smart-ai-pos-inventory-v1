<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Kebijakan kuota AI berjangka waktu — bawaan platform dan promo.
 *
 * Dibaca HANYA oleh `App\Services\Ai\AiQuota`. Larangan itu bukan formalitas:
 * seluruh sebab `[BL-047]`(b) ada adalah agar angka yang dibacakan ke owner
 * dan angka yang menolak permintaannya mustahil berselisih, dan pembaca kedua
 * mana pun mengembalikan kemungkinan itu.
 *
 * @see \App\Services\Ai\AiQuota
 */
class AiQuotaPolicy extends Model
{
    /** @use HasFactory<\Database\Factories\AiQuotaPolicyFactory> */
    use HasFactory;

    use SoftDeletes;

    /** Kuota bawaan platform bagi tenant yang paketnya tidak menetapkan batas. */
    public const MODE_BASELINE = 'baseline';

    /** Tambahan di ATAS batas yang berlaku — inilah bentuk sebuah promo. */
    public const MODE_BONUS = 'bonus';

    protected $fillable = ['label', 'mode', 'daily_limit', 'effective_from', 'effective_until'];

    protected function casts(): array
    {
        return [
            'daily_limit' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    /**
     * @return list<string>
     */
    public static function allModes(): array
    {
        return [self::MODE_BASELINE, self::MODE_BONUS];
    }

    // --- Scopes ---

    /**
     * Kebijakan yang berlaku pada tanggal tertentu.
     *
     * `effective_until` null berarti belum ada tanggal akhirnya.
     */
    public function scopeEffectiveOn(Builder $query, ?Carbon $date = null): Builder
    {
        $date ??= now();

        return $query
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $q) => $q
                ->whereNull('effective_until')
                ->orWhereDate('effective_until', '>=', $date));
    }

    /**
     * Kebijakan yang menang untuk sebuah mode pada tanggal tertentu.
     *
     * Yang paling BARU berlaku menang, bukan yang berprioritas tertinggi.
     * `pricing_rules` memakai kolom `priority` karena aturannya dinilai
     * berdampingan — banyak aturan bisa cocok untuk satu tenant sekaligus, dan
     * yang menentukan pemenangnya harus disebut terang-terangan. Di sini tidak
     * ada dimensi yang dibandingkan: dua kebijakan yang tumpang tindih selalu
     * berarti yang belakangan diterbitkan dimaksudkan menggantikan yang
     * sebelumnya. Satu kolom lebih sedikit untuk dijelaskan, dan jawabannya
     * tetap tunggal.
     */
    public static function winnerFor(string $mode, ?Carbon $date = null): ?self
    {
        return static::query()
            ->where('mode', $mode)
            ->effectiveOn($date)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    // --- Helpers ---

    public function isEffective(?Carbon $date = null): bool
    {
        $date ??= now();

        return ! $this->effective_from->startOfDay()->isAfter($date)
            && ($this->effective_until === null || ! $this->effective_until->endOfDay()->isBefore($date));
    }

    public function hasEnded(?Carbon $date = null): bool
    {
        return $this->effective_until !== null
            && $this->effective_until->endOfDay()->isBefore($date ?? now());
    }

    /**
     * Bentuk yang layak masuk jejak audit dan props panel.
     *
     * @return array{label: string, mode: string, daily_limit: int, effective_from: string, effective_until: string|null}
     */
    public function summary(): array
    {
        return [
            'label' => $this->label,
            'mode' => $this->mode,
            'daily_limit' => $this->daily_limit,
            'effective_from' => $this->effective_from->toDateString(),
            'effective_until' => $this->effective_until?->toDateString(),
        ];
    }
}

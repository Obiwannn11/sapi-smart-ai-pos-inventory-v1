<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    /**
     * Slug paket dasar yang selalu ada — ditulis oleh migrasi `plans`, dipakai
     * saat registrasi memberi trial. Merujuk slug, bukan id, supaya tidak
     * terikat auto-increment yang bisa berbeda antar pemasangan.
     */
    public const SLUG_DEFAULT = 'dasar';

    protected $fillable = [
        'name', 'slug', 'base_price', 'included_seats', 'extra_seat_price', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'extra_seat_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // --- Relationships ---
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // --- Helpers ---

    /**
     * Paket dasar bawaan. Dipakai registrasi tenant baru.
     */
    public static function default(): self
    {
        return static::where('slug', self::SLUG_DEFAULT)->firstOrFail();
    }
}

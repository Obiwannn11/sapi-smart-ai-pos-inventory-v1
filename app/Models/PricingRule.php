<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PricingRule extends Model
{
    /** @use HasFactory<\Database\Factories\PricingRuleFactory> */
    use HasFactory;

    protected $fillable = ['label', 'min_revenue', 'max_revenue', 'price', 'effective_from'];

    protected function casts(): array
    {
        return [
            'min_revenue' => 'decimal:2',
            'max_revenue' => 'decimal:2',
            'price' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    // --- Scopes ---

    /**
     * Aturan yang sudah berlaku pada tanggal tertentu.
     *
     * Inilah penegakan grandfathering di lapisan query: aturan yang dibuat
     * dengan `effective_from` di masa depan tidak ikut terbaca sampai
     * tanggalnya tiba, sehingga menyunting tarif hari ini tidak mengubah apa
     * pun yang sedang berjalan.
     */
    public function scopeEffectiveOn(Builder $query, ?Carbon $date = null): Builder
    {
        return $query->whereDate('effective_from', '<=', $date ?? now());
    }

    // --- Helpers ---
    public function covers(float $revenue): bool
    {
        return $revenue >= (float) $this->min_revenue
            && ($this->max_revenue === null || $revenue < (float) $this->max_revenue);
    }
}

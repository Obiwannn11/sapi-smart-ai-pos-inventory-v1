<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PricingRule extends Model
{
    /** @use HasFactory<\Database\Factories\PricingRuleFactory> */
    use HasFactory;

    protected $fillable = ['label', 'priority', 'price', 'effective_from'];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'price' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    // --- Relationships ---
    public function conditions(): HasMany
    {
        return $this->hasMany(PricingRuleCondition::class);
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

    /**
     * Apakah SELURUH syarat aturan ini terpenuhi oleh konteks yang diberikan.
     *
     * Konteks memetakan nama dimensi ke nilainya; dimensi yang tidak ada di
     * dalamnya bernilai `null` dan karenanya menggugurkan aturan. Aturan tanpa
     * syarat sama sekali cocok untuk siapa pun — itulah cara menuliskan tarif
     * bawaan, dan sebaiknya diberi `priority` terendah supaya tidak pernah
     * mendahului aturan yang lebih spesifik.
     *
     * @param  array<string, float|string|null>  $context
     */
    public function matches(array $context): bool
    {
        return $this->conditions->every(
            fn (PricingRuleCondition $condition) => $condition->isSatisfiedBy($context[$condition->dimension] ?? null)
        );
    }

    /**
     * Ringkasan syarat untuk ditampilkan di panel dan disimpan di jejak audit.
     *
     * @return list<array{dimension: string, operator: string, value: string}>
     */
    public function conditionSummary(): array
    {
        return $this->conditions
            ->map(fn (PricingRuleCondition $condition) => [
                'dimension' => $condition->dimension,
                'operator' => $condition->operator,
                'value' => $condition->value,
            ])
            ->values()
            ->all();
    }
}

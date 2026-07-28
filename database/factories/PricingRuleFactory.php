<?php

namespace Database\Factories;

use App\Models\PricingRule;
use App\Models\PricingRuleCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PricingRule>
 */
class PricingRuleFactory extends Factory
{
    protected $model = PricingRule::class;

    /**
     * Aturan tanpa satu pun syarat — dan karena itu cocok untuk siapa pun.
     *
     * Sengaja demikian: syarat ditambahkan sadar-sadar lewat state di bawah,
     * sehingga tiap test menyatakan sendiri aturan macam apa yang sedang diuji
     * alih-alih mewarisi rentang omzet yang kebetulan ada di bawaan factory.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => strtoupper(fake()->unique()->randomLetter()),
            'priority' => 0,
            'price' => 10_000,
            'effective_from' => now()->subYear()->toDateString(),
        ];
    }

    public function effectiveFrom(string $date): static
    {
        return $this->state(fn (array $attributes) => ['effective_from' => $date]);
    }

    public function priority(int $priority): static
    {
        return $this->state(fn (array $attributes) => ['priority' => $priority]);
    }

    /**
     * Bracket omzet klasik: batas bawah inklusif, batas atas eksklusif.
     *
     * Batas atasnya eksklusif supaya omzet yang tepat di perbatasan tidak cocok
     * pada dua bracket sekaligus — perilaku yang sama dengan bracket sebelum
     * `[BL-015]`.
     */
    public function revenueBetween(float $min, ?float $max = null): static
    {
        return $this->withCondition('monthly_revenue', PricingRuleCondition::OP_GTE, (string) $min)
            ->when($max !== null, fn (self $factory) => $factory->withCondition(
                'monthly_revenue',
                PricingRuleCondition::OP_LT,
                (string) $max,
            ));
    }

    public function withCondition(string $dimension, string $operator, string $value): static
    {
        return $this->afterCreating(fn (PricingRule $rule) => $rule->conditions()->create([
            'dimension' => $dimension,
            'operator' => $operator,
            'value' => $value,
        ]));
    }
}

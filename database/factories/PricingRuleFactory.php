<?php

namespace Database\Factories;

use App\Models\PricingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PricingRule>
 */
class PricingRuleFactory extends Factory
{
    protected $model = PricingRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => strtoupper(fake()->unique()->randomLetter()),
            'min_revenue' => 0,
            'max_revenue' => 1_000_000,
            'price' => 10_000,
            'effective_from' => now()->subYear()->toDateString(),
        ];
    }

    public function effectiveFrom(string $date): static
    {
        return $this->state(fn (array $attributes) => ['effective_from' => $date]);
    }
}

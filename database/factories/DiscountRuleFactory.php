<?php

namespace Database\Factories;

use App\Models\DiscountRule;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiscountRule>
 */
class DiscountRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'trigger' => DiscountRule::TRIGGER_MANUAL,
            'percent' => 20,
            'max_percent' => null,
            'reason' => 'Promo akhir pekan',
            'starts_on' => null,
            'ends_on' => null,
            'is_active' => true,
        ];
    }

    /** Potongan yang mendalam seiring tanggal kedaluwarsa mendekat. */
    public function nearExpiry(float $percent = 10, float $maxPercent = 50): static
    {
        return $this->state(fn () => [
            'trigger' => DiscountRule::TRIGGER_NEAR_EXPIRY,
            'percent' => $percent,
            'max_percent' => $maxPercent,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_on' => now()->subDays(30)->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'starts_on' => now()->addDays(7)->toDateString(),
            'ends_on' => now()->addDays(14)->toDateString(),
        ]);
    }
}

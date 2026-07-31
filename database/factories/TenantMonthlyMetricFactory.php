<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantMonthlyMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TenantMonthlyMetric>
 */
class TenantMonthlyMetricFactory extends Factory
{
    protected $model = TenantMonthlyMetric::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'period' => now()->startOfMonth()->subMonth()->format('Y-m'),
            'revenue' => fake()->numberBetween(500, 20000) * 1000,
            'transaction_count' => fake()->numberBetween(10, 2000),
            'computed_at' => now(),
        ];
    }

    public function forPeriod(string $period): static
    {
        return $this->state(fn (array $attributes) => ['period' => $period]);
    }

    public function revenue(float $revenue): static
    {
        return $this->state(fn (array $attributes) => ['revenue' => $revenue]);
    }
}

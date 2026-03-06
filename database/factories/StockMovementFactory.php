<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'type' => 'restock',
            'qty' => fake()->numberBetween(1, 100),
            'notes' => fake()->sentence(),
            'reference_id' => null,
        ];
    }

    public function sale(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'sale',
            'qty' => fake()->numberBetween(-20, -1),
        ]);
    }

    public function void(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'void',
        ]);
    }
}

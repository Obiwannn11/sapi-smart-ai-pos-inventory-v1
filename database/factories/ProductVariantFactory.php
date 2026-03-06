<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->word() . ' ' . fake()->randomElement(['S', 'M', 'L', 'XL']),
            'sku' => fake()->unique()->bothify('SKU-####-??'),
            'price' => fake()->numberBetween(5000, 100000),
            'cost_price' => fake()->numberBetween(2000, 50000),
            'stock' => fake()->numberBetween(0, 200),
            'expiry_date' => null,
        ];
    }

    public function withExpiry(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => $date,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => fake()->numberBetween(1, 5),
        ]);
    }
}

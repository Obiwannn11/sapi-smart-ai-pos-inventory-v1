<?php

namespace Database\Factories;

use App\Models\ExpiredStockRecord;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExpiredStockRecord>
 */
class ExpiredStockRecordFactory extends Factory
{
    protected $model = ExpiredStockRecord::class;

    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 30);
        $costPrice = fake()->numberBetween(2000, 50000);

        return [
            'tenant_id' => Tenant::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'label' => fake()->words(2, true),
            'expiry_date' => now()->subDay()->toDateString(),
            'recorded_on' => now()->toDateString(),
            'qty' => $qty,
            'cost_price' => $costPrice,
            'value' => $qty * $costPrice,
            'source' => ExpiredStockRecord::SOURCE_RECORDER,
        ];
    }

    /**
     * Barang yang sudah basi sebelum pencatatnya ada — jumlahnya tidak diketahui.
     */
    public function preExisting(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => ExpiredStockRecord::SOURCE_PRE_EXISTING,
            'qty' => null,
            'cost_price' => null,
            'value' => null,
        ]);
    }
}

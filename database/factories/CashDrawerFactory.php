<?php

namespace Database\Factories;

use App\Models\CashDrawer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashDrawer>
 */
class CashDrawerFactory extends Factory
{
    protected $model = CashDrawer::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'opening_amount' => 500000,
            'closing_amount' => null,
            'expected_amount' => null,
            'difference' => null,
            'notes' => null,
            'opened_at' => now(),
            'closed_at' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'closing_amount' => 750000,
            'expected_amount' => 700000,
            'difference' => 50000,
            'closed_at' => now(),
        ]);
    }
}

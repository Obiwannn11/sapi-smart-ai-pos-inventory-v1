<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Cash',
            'type' => 'cash',
            'is_active' => true,
        ];
    }

    public function qris(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'QRIS',
            'type' => 'qris_static',
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Bank Transfer',
            'type' => 'bank_transfer',
        ]);
    }
}

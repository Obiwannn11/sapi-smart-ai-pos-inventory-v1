<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'code' => 'TRX-' . now()->format('Ymd') . '-' . str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'status' => Transaction::STATUS_COMPLETED,
            'total_amount' => fake()->numberBetween(10000, 500000),
            'change_amount' => 0,
            'notes' => null,
            'source' => Transaction::SOURCE_POS,
            'order_type' => Transaction::ORDER_TYPE_DINE_IN,
            'fulfillment_status' => null,
            'customer_name' => null,
            'table_number' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Transaction::STATUS_PENDING,
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Transaction::STATUS_VOIDED,
        ]);
    }

    public function selfOrder(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => Transaction::SOURCE_SELF_ORDER,
            'fulfillment_status' => Transaction::FULFILLMENT_WAITING,
            'customer_name' => fake()->firstName(),
        ]);
    }

    public function withFulfillment(string $status = Transaction::FULFILLMENT_WAITING): static
    {
        return $this->state(fn (array $attributes) => [
            'fulfillment_status' => $status,
        ]);
    }

    public function pickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_type' => Transaction::ORDER_TYPE_PICKUP,
        ]);
    }
}

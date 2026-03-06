<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TransactionPayment>
 */
class TransactionPaymentFactory extends Factory
{
    protected $model = TransactionPayment::class;

    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'amount' => fake()->numberBetween(10000, 500000),
            'reference_code' => null,
        ];
    }
}

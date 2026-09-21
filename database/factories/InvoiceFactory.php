<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subscription = Subscription::factory();

        return [
            'subscription_id' => $subscription,
            // Tagihan selalu milik tenant yang sama dengan langganannya.
            // Diturunkan dari langganan, bukan dibuat sendiri, supaya tidak
            // pernah ada tagihan yang menunjuk dua tenant berbeda.
            'tenant_id' => fn (array $attributes) => Subscription::find($attributes['subscription_id'])?->tenant_id,
            'period' => now()->format('Y-m'),
            'amount' => fake()->numberBetween(10, 500) * 1000,
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => now()->addWeek()->toDateString(),
        ];
    }

    public function awaitingVerification(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_AWAITING_VERIFICATION,
            'proof_path' => 'proofs/contoh.jpg',
            'submitted_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_PAID,
            'paid_at' => now(),
            'verified_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_REJECTED,
            'rejection_reason' => 'Nominal transfer kurang dari tagihan.',
            'verified_at' => now(),
        ]);
    }
}

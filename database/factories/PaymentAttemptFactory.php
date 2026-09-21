<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invoice = Invoice::factory();

        return [
            'invoice_id' => $invoice,
            // Diturunkan dari tagihannya, bukan dibuat sendiri — sama seperti
            // InvoiceFactory menurunkan tenant dari langganan. Percobaan
            // pembayaran yang menunjuk tenant lain dari tagihannya adalah
            // keadaan yang tidak boleh bisa dibuat, termasuk di test.
            'tenant_id' => fn (array $attributes) => Invoice::find($attributes['invoice_id'])?->tenant_id,
            'amount' => fn (array $attributes) => Invoice::find($attributes['invoice_id'])?->amount,
            'gateway' => 'fake',
            'channel' => 'qris',
            'external_id' => fake()->unique()->numerify('FAKE-########'),
            'status' => PaymentAttempt::STATUS_PENDING,
            'expires_at' => now()->addHour(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentAttempt::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TaxCalculator;
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
            'code' => 'TRX-'.now()->format('Ymd').'-'.str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'status' => Transaction::STATUS_COMPLETED,
            'total_amount' => fake()->numberBetween(10000, 500000),
            // Bawaannya penjualan TANPA pajak, dan itu berarti subtotalnya sama
            // dengan totalnya — bukan nol. Closure, bukan nilai tetap: hampir
            // setiap test menimpa `total_amount`, dan subtotal yang tertinggal
            // di angka lain akan melanggar invarian subtotal + pajak = total.
            'subtotal_amount' => fn (array $attributes) => $attributes['total_amount'],
            'tax_amount' => 0,
            'change_amount' => 0,
            'notes' => null,
            'source' => Transaction::SOURCE_POS,
            'order_type' => Transaction::ORDER_TYPE_DINE_IN,
            'fulfillment_status' => null,
            'customer_name' => null,
            'table_number' => null,
        ];
    }

    /**
     * Penjualan yang memungut pajak, dengan konteksnya ikut dibekukan.
     *
     * Angkanya diturunkan lewat `TaxCalculator` yang sama dengan produksi,
     * bukan dihitung ulang di sini: test yang memakai aritmetika sendiri akan
     * lulus terhadap kesalahannya sendiri.
     *
     * `$base` adalah jumlah barisnya sebelum pajak disentuh, dan artinya
     * mengikuti modenya — subtotal di mode exclusive, total di mode inclusive.
     * Ia dipakai sebagai argumen, BUKAN dibaca dari `total_amount`: atribut
     * yang diberikan ke `create()` menimpa state, jadi `total_amount` yang
     * dititipkan di sana akan menyisakan pajak dari angka acak bawaan pabrik.
     * Karena itu jangan menyetel `total_amount` sendiri bersama state ini —
     * ketiga angka uangnya lahir dari sini.
     */
    public function taxed(
        float $base,
        float $rate = 11,
        string $mode = Tenant::TAX_MODE_EXCLUSIVE,
        string $label = 'PPN',
    ): static {
        return $this->state(fn (array $attributes) => (new TaxCalculator)->columnsFor(
            $base,
            ['enabled' => true, 'mode' => $mode, 'rate' => $rate, 'label' => $label],
        ));
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

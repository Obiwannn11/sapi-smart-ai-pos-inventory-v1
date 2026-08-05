<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            // Paket dasar sudah ditulis oleh migrasi `plans`, jadi pakai itu
            // alih-alih membuat paket baru tiap langganan — test yang menghitung
            // paket akan tersesat kalau tiap langganan menambah satu.
            'plan_id' => Plan::query()->where('slug', Plan::SLUG_DEFAULT)->value('id') ?? Plan::factory(),
            'pricing_track' => Subscription::TRACK_NORMAL,
            'seats' => 1,
            'seat_high_water' => 1,
            'price_locked' => 0,
            // `addMonthNoOverflow`, sejalan dengan `[BL-030]`: `addMonth()`
            // telanjang membuat langganan yang lahir 31 Januari berakhir 3 Maret,
            // dan factory yang meniru cacat produksi akan meloloskan test yang
            // seharusnya menangkapnya.
            'trial_ends_at' => now()->addMonthNoOverflow(),
            'current_period_start' => now()->toDateString(),
            'current_period_end' => now()->addMonthNoOverflow()->toDateString(),
            'billing_anchor_day' => now()->addMonthNoOverflow()->day,
        ];
    }

    public function subsidized(): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_track' => Subscription::TRACK_SUBSIDIZED,
        ]);
    }

    public function seats(int $seats): static
    {
        return $this->state(fn (array $attributes) => [
            'seats' => $seats,
            'seat_high_water' => $seats,
        ]);
    }

    public function trialEnded(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => now()->subDay(),
        ]);
    }
}

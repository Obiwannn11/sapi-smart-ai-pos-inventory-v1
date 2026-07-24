<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
        ];
    }

    /**
     * Sengaja TIDAK membuat langganan di sini. Membuatnya otomatis akan
     * bertabrakan dengan `SubscriptionFactory` (yang membuat tenant sendiri)
     * pada indeks unik `subscriptions.tenant_id`. Langganan dijamin ada lewat
     * `SubscriptionService::ensureFor()` yang idempoten — dipanggil saat
     * registrasi dan di tiap titik yang benar-benar membutuhkannya.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Tenant::STATUS_ACTIVE,
        ]);
    }

    public function readOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Tenant::STATUS_GRACE,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Tenant::STATUS_SUSPENDED,
        ]);
    }

    public function subsidized(): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_track' => \App\Models\Subscription::TRACK_SUBSIDIZED,
        ]);
    }
}

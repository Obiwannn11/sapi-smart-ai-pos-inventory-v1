<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\UpsellEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UpsellEvent>
 */
class UpsellEventFactory extends Factory
{
    protected $model = UpsellEvent::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'transaction_id' => null,
            'type' => UpsellEvent::TYPE_ATTACH,
            'surface' => UpsellEvent::SURFACE_POS,
            'status' => UpsellEvent::STATUS_IGNORED,
            'reason' => UpsellEvent::REASON_COOCCURRENCE,
            'trigger_variant_id' => null,
            'suggested_variant_id' => null,
            'suggested_modifier_id' => null,
            'label' => fake()->words(2, true),
            'extra_amount' => 0,
        ];
    }

    public function accepted(float $extraAmount = 5000): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UpsellEvent::STATUS_ACCEPTED,
            'extra_amount' => $extraAmount,
        ]);
    }

    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }

    public function selfOrder(): static
    {
        return $this->state(fn (array $attributes) => [
            'surface' => UpsellEvent::SURFACE_SELF_ORDER,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UpsellRule>
 */
class UpsellRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            // Bawaannya aturan tanpa pemicu — bentuk paling sederhana, dan yang
            // paling sering dipakai test sebagai titik awal.
            'trigger_variant_id' => null,
            'suggested_variant_id' => ProductVariant::factory(),
            'note' => null,
            'starts_on' => null,
            'ends_on' => null,
            'priority' => 0,
            'is_active' => true,
        ];
    }

    /** Aturan berpemicu: "kalau beli A, tawarkan B". */
    public function triggeredBy(ProductVariant $variant): static
    {
        return $this->state(fn () => ['trigger_variant_id' => $variant->id]);
    }

    public function suggesting(ProductVariant $variant): static
    {
        return $this->state(fn () => ['suggested_variant_id' => $variant->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /** Jendela berlaku yang sudah lewat. */
    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_on' => now()->subDays(30)->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
        ]);
    }

    /** Dijadwalkan dari jauh hari — belum boleh muncul. */
    public function scheduled(): static
    {
        return $this->state(fn () => [
            'starts_on' => now()->addDays(7)->toDateString(),
            'ends_on' => now()->addDays(14)->toDateString(),
        ]);
    }
}

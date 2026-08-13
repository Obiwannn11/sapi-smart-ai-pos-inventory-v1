<?php

namespace Database\Factories;

use App\Models\AiQuotaPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiQuotaPolicy>
 */
class AiQuotaPolicyFactory extends Factory
{
    protected $model = AiQuotaPolicy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => 'Kebijakan '.fake()->word(),
            'mode' => AiQuotaPolicy::MODE_BASELINE,
            'daily_limit' => fake()->numberBetween(1, 50),
            'effective_from' => now()->subDay()->toDateString(),
            'effective_until' => null,
        ];
    }

    public function baseline(int $limit): static
    {
        return $this->state(['mode' => AiQuotaPolicy::MODE_BASELINE, 'daily_limit' => $limit]);
    }

    public function bonus(int $limit): static
    {
        return $this->state(['mode' => AiQuotaPolicy::MODE_BONUS, 'daily_limit' => $limit]);
    }

    /** Sudah lewat tanggal akhirnya — dipakai menguji promo yang kedaluwarsa sendiri. */
    public function expired(): static
    {
        return $this->state([
            'effective_from' => now()->subDays(10)->toDateString(),
            'effective_until' => now()->subDay()->toDateString(),
        ]);
    }

    /** Belum mulai berlaku. */
    public function upcoming(): static
    {
        return $this->state([
            'effective_from' => now()->addDays(3)->toDateString(),
            'effective_until' => null,
        ]);
    }
}

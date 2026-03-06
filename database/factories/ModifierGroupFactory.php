<?php

namespace Database\Factories;

use App\Models\ModifierGroup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ModifierGroup>
 */
class ModifierGroupFactory extends Factory
{
    protected $model = ModifierGroup::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(2, true),
            'is_required' => false,
            'is_multiple' => false,
        ];
    }
}

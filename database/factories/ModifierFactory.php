<?php

namespace Database\Factories;

use App\Models\Modifier;
use App\Models\ModifierGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Modifier>
 */
class ModifierFactory extends Factory
{
    protected $model = Modifier::class;

    public function definition(): array
    {
        return [
            'modifier_group_id' => ModifierGroup::factory(),
            'name' => fake()->word(),
            'extra_price' => fake()->randomFloat(2, 1000, 10000),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\PricingRule;
use App\Models\PricingRuleCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PricingRuleCondition>
 */
class PricingRuleConditionFactory extends Factory
{
    protected $model = PricingRuleCondition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pricing_rule_id' => PricingRule::factory(),
            'dimension' => 'monthly_revenue',
            'operator' => PricingRuleCondition::OP_GTE,
            'value' => '0',
        ];
    }
}

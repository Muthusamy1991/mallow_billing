<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Models\Merchant;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'name' => fake()->unique()->randomElement(['Starter', 'Growth', 'Scale', 'Enterprise']).' '.fake()->randomNumber(3),
            'base_price' => fake()->randomElement(['49.0000', '149.0000', '399.0000']),
            'billing_cycle' => BillingCycle::Monthly,
            'included_units' => fake()->randomElement([1000, 10000, 50000]),
            'overage_rate' => fake()->randomElement(['0.010000', '0.008000', '0.005000']),
            'is_active' => true,
        ];
    }
}

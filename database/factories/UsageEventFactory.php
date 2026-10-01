<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\UsageEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UsageEvent>
 */
class UsageEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'customer_id' => Customer::factory(),
            'units' => fake()->numberBetween(1, 500),
            'usage_date' => now()->toDateString(),
            'idempotency_key' => (string) Str::uuid(),
            'created_at' => now(),
        ];
    }
}

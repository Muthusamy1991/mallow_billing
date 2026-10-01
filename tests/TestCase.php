<?php

namespace Tests;

use App\Enums\BillingCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-01 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function merchantWithPlan(array $planOverrides = []): array
    {
        $merchant = Merchant::factory()->create([
            'api_key' => 'test-merchant-key',
        ]);

        $plan = Plan::factory()->create(array_merge([
            'merchant_id' => $merchant->id,
            'name' => 'Starter',
            'base_price' => '100.0000',
            'billing_cycle' => BillingCycle::Monthly,
            'included_units' => 1000,
            'overage_rate' => '0.100000',
        ], $planOverrides));

        return [$merchant, $plan];
    }

    protected function subscribeCustomer(
        Merchant $merchant,
        Plan $plan,
        CarbonInterface $startedAt,
        array $customerOverrides = [],
    ): Customer {
        $customer = Customer::factory()->create(array_merge([
            'merchant_id' => $merchant->id,
        ], $customerOverrides));

        app(SubscriptionService::class)->subscribe($customer, $plan, $startedAt);

        return $customer->fresh();
    }
}

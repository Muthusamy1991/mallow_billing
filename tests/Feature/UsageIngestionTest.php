<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\UsageDailyAggregate;
use App\Models\UsageEvent;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class UsageIngestionTest extends TestCase
{
    public function test_it_records_usage_and_bumps_the_daily_aggregate(): void
    {
        [$merchant, $plan] = $this->merchantWithPlan();
        $customer = $this->subscribeCustomer($merchant, $plan, Carbon::parse('2026-01-01'));
        $key = (string) Str::uuid();

        $response = $this->withHeaders([
            'X-Api-Key' => $merchant->api_key,
            'Idempotency-Key' => $key,
        ])->postJson('/api/usage', [
            'customer_id' => $customer->id,
            'units' => 25,
            'usage_date' => '2026-01-15',
            'idempotency_key' => $key,
        ]);

        $response->assertCreated()->assertJsonPath('idempotent_replay', false);
        $this->assertDatabaseCount('usage_events', 1);
        $this->assertDatabaseHas('usage_daily_aggregates', [
            'customer_id' => $customer->id,
            'total_units' => 25,
        ]);
    }

    public function test_retried_request_with_the_same_idempotency_key_does_not_double_count(): void
    {
        [$merchant, $plan] = $this->merchantWithPlan();
        $customer = $this->subscribeCustomer($merchant, $plan, Carbon::parse('2026-01-01'));
        $payload = [
            'customer_id' => $customer->id,
            'units' => 40,
            'usage_date' => '2026-01-15',
            'idempotency_key' => '11111111-1111-1111-1111-111111111111',
        ];

        $this->withHeaders(['X-Api-Key' => $merchant->api_key])->postJson('/api/usage', $payload)->assertCreated();
        $replay = $this->withHeaders(['X-Api-Key' => $merchant->api_key])->postJson('/api/usage', $payload);

        $replay->assertOk()->assertJsonPath('idempotent_replay', true);
        $this->assertSame(1, UsageEvent::query()->count());
        $this->assertSame(40, (int) UsageDailyAggregate::query()->value('total_units'));
    }

    public function test_it_rejects_usage_for_another_merchant_customer(): void
    {
        [$merchant, $plan] = $this->merchantWithPlan();
        $this->subscribeCustomer($merchant, $plan, Carbon::parse('2026-01-01'));

        $other = Merchant::factory()->create(['api_key' => 'other-key']);
        $foreign = Customer::factory()->create(['merchant_id' => $other->id]);

        $this->withHeaders(['X-Api-Key' => $merchant->api_key])
            ->postJson('/api/usage', [
                'customer_id' => $foreign->id,
                'units' => 10,
                'usage_date' => '2026-01-15',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertUnprocessable();
    }

    public function test_missing_api_key_is_unauthorized(): void
    {
        $this->postJson('/api/usage', [
            'customer_id' => 1,
            'units' => 1,
            'usage_date' => '2026-01-01',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertUnauthorized();
    }

    public function test_usage_endpoint_is_rate_limited(): void
    {
        config(['billing.usage_rate_limit_per_minute' => 1]);

        [$merchant, $plan] = $this->merchantWithPlan();
        $customer = $this->subscribeCustomer($merchant, $plan, Carbon::parse('2026-01-01'));

        $this->withHeaders(['X-Api-Key' => $merchant->api_key])
            ->postJson('/api/usage', [
                'customer_id' => $customer->id,
                'units' => 1,
                'usage_date' => '2026-01-02',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertCreated();

        $this->withHeaders(['X-Api-Key' => $merchant->api_key])
            ->postJson('/api/usage', [
                'customer_id' => $customer->id,
                'units' => 1,
                'usage_date' => '2026-01-02',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertStatus(429);
    }
}

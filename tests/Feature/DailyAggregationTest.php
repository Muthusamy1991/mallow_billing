<?php

namespace Tests\Feature;

use App\Jobs\AggregateDailyUsageJob;
use App\Models\UsageDailyAggregate;
use App\Models\UsageEvent;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyAggregationTest extends TestCase
{
    public function test_chunked_job_rebuilds_daily_totals_from_raw_events(): void
    {
        [$merchant, $plan] = $this->merchantWithPlan();
        $customer = $this->subscribeCustomer($merchant, $plan, Carbon::parse('2026-02-01'));

        UsageEvent::factory()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'units' => 10,
            'usage_date' => '2026-02-10',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        UsageEvent::factory()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'units' => 15,
            'usage_date' => '2026-02-10',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        UsageDailyAggregate::query()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-02-10',
            'total_units' => 1,
        ]);

        (new AggregateDailyUsageJob('2026-02-10'))->handle(app(\App\Services\DailyUsageAggregator::class));

        $this->assertSame(25, (int) UsageDailyAggregate::query()->where('customer_id', $customer->id)->value('total_units'));
    }
}

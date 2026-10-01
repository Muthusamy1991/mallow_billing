<?php

namespace Tests\Feature;

use App\Models\UsageDailyAggregate;
use Carbon\Carbon;
use Tests\TestCase;

class MerchantDashboardTest extends TestCase
{
    public function test_dashboard_returns_top_customers_projected_overage_and_churn_risk(): void
    {
        Carbon::setTestNow('2026-10-15');

        [$merchant, $plan] = $this->merchantWithPlan([
            'included_units' => 100,
            'overage_rate' => '1.000000',
        ]);

        $top = $this->subscribeCustomer($merchant, $plan, Carbon::parse('2026-08-01'), ['name' => 'Top Customer']);
        $quiet = $this->subscribeCustomer($merchant, $plan, Carbon::parse('2026-08-01'), ['name' => 'Quiet Customer']);
        $risk = $this->subscribeCustomer($merchant, $plan, Carbon::parse('2026-08-01'), ['name' => 'Risk Customer']);

        $this->seedRange($merchant->id, $top->id, '2026-09-01', '2026-09-15', 100);
        $this->seedRange($merchant->id, $top->id, '2026-10-01', '2026-10-15', 400);

        $this->seedRange($merchant->id, $quiet->id, '2026-10-01', '2026-10-15', 10);

        $this->seedRange($merchant->id, $risk->id, '2026-09-01', '2026-09-15', 1000);
        $this->seedRange($merchant->id, $risk->id, '2026-10-01', '2026-10-15', 100);

        $response = $this->getJson("/api/merchants/{$merchant->id}/dashboard");

        $response->assertOk()
            ->assertJsonPath('top_customers.0.name', 'Top Customer')
            ->assertJsonPath('churn_risk.0.name', 'Risk Customer');

        $this->assertGreaterThan(0, (float) $response->json('kpis.projected_overage_revenue'));
        $this->assertSame(1, $response->json('kpis.churn_risk_count'));
    }

    public function test_plan_updates_flush_plan_cache(): void
    {
        [$merchant, $plan] = $this->merchantWithPlan();
        $cache = app(\App\Services\PlanCache::class);

        $this->assertSame('Starter', $cache->find($plan->id)->name);

        $plan->update(['name' => 'Starter Plus']);

        $this->assertSame('Starter Plus', $cache->find($plan->id)->name);
    }

    private function seedRange(int $merchantId, int $customerId, string $from, string $to, int $unitsPerDay): void
    {
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);

        while ($cursor->lte($end)) {
            UsageDailyAggregate::query()->create([
                'merchant_id' => $merchantId,
                'customer_id' => $customerId,
                'usage_date' => $cursor->toDateString(),
                'total_units' => $unitsPerDay,
            ]);
            $cursor->addDay();
        }
    }
}

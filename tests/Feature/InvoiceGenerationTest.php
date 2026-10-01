<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Models\Plan;
use App\Models\UsageDailyAggregate;
use App\Services\InvoiceService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Tests\TestCase;

class InvoiceGenerationTest extends TestCase
{
    public function test_it_prorates_a_mid_month_subscription_and_applies_overage(): void
    {
        [$merchant, $plan] = $this->merchantWithPlan();
        $customer = $this->subscribeCustomer($merchant, $plan, Carbon::parse('2026-01-10'));

        UsageDailyAggregate::query()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-01-20',
            'total_units' => 800,
        ]);

        $invoice = app(InvoiceService::class)->generateForSubscription(
            $customer->activeSubscription,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-31'),
        );

        $this->assertNotNull($invoice);
        $this->assertSame('70.9677', (string) $invoice->base_amount);
        $this->assertSame('9.1000', (string) $invoice->overage_amount);
        $this->assertSame('80.0677', (string) $invoice->total_amount);
        $this->assertCount(2, $invoice->lineItems);
    }

    public function test_mid_cycle_upgrade_keeps_pre_change_usage_on_the_old_rate(): void
    {
        [$merchant, $starter] = $this->merchantWithPlan();
        $growth = Plan::factory()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Growth',
            'base_price' => '200.0000',
            'billing_cycle' => BillingCycle::Monthly,
            'included_units' => 5000,
            'overage_rate' => '0.050000',
        ]);

        $customer = $this->subscribeCustomer($merchant, $starter, Carbon::parse('2026-01-01'));
        app(SubscriptionService::class)->changePlan($customer, $growth, Carbon::parse('2026-01-15'));

        UsageDailyAggregate::query()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-01-10',
            'total_units' => 600,
        ]);
        UsageDailyAggregate::query()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-01-20',
            'total_units' => 100,
        ]);

        $invoice = app(InvoiceService::class)->generateForSubscription(
            $customer->fresh()->activeSubscription,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-31'),
        );

        $this->assertSame('154.8386', (string) $invoice->base_amount);
        $this->assertSame('14.9000', (string) $invoice->overage_amount);
        $this->assertSame('169.7386', (string) $invoice->total_amount);

        $overageLines = $invoice->lineItems()->where('type', 'overage')->orderBy('id')->get();
        $this->assertSame($starter->id, $overageLines->first()->plan_id);
        $this->assertSame($growth->id, $overageLines->last()->plan_id);
        $this->assertSame(149, (int) $overageLines->first()->overage_units);
        $this->assertSame(0, (int) $overageLines->last()->overage_units);
    }

    public function test_generating_the_same_cycle_twice_does_not_duplicate_invoices(): void
    {
        [$merchant, $plan] = $this->merchantWithPlan();
        $customer = $this->subscribeCustomer($merchant, $plan, Carbon::parse('2026-01-01'));

        $service = app(InvoiceService::class);
        $first = $service->generateForSubscription(
            $customer->activeSubscription,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-31'),
        );
        $second = $service->generateForSubscription(
            $customer->fresh()->activeSubscription,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-31'),
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->number, $second->number);
        $this->assertDatabaseCount('invoices', 1);
    }
}

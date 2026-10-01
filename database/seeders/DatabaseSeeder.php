<?php

namespace Database\Seeders;

use App\Enums\BillingCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\UsageDailyAggregate;
use App\Models\UsageEvent;
use App\Services\InvoiceService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::query()->create([
            'name' => 'Northwind SaaS',
            'slug' => 'northwind-saas',
            'email' => 'billing@northwind.test',
            'api_key' => 'demo-northwind-key',
        ]);

        $starter = Plan::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Starter',
            'base_price' => '49.0000',
            'billing_cycle' => BillingCycle::Monthly,
            'included_units' => 10000,
            'overage_rate' => '0.010000',
            'is_active' => true,
        ]);

        $growth = Plan::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Growth',
            'base_price' => '149.0000',
            'billing_cycle' => BillingCycle::Monthly,
            'included_units' => 50000,
            'overage_rate' => '0.008000',
            'is_active' => true,
        ]);

        $scale = Plan::query()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Scale',
            'base_price' => '399.0000',
            'billing_cycle' => BillingCycle::Monthly,
            'included_units' => 200000,
            'overage_rate' => '0.005000',
            'is_active' => true,
        ]);

        $subscriptions = app(SubscriptionService::class);
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->copy()->subMonth()->startOfMonth();

        $profiles = [
            ['Aurora Labs', 'aurora@northwind.test', $growth, $lastMonth->copy()->day(3), 'high'],
            ['Beacon Media', 'beacon@northwind.test', $scale, $lastMonth->copy()->day(1), 'high'],
            ['Cobalt Health', 'cobalt@northwind.test', $growth, $lastMonth->copy()->day(8), 'high'],
            ['Drift Commerce', 'drift@northwind.test', $starter, $lastMonth->copy()->day(5), 'overage'],
            ['Ember Analytics', 'ember@northwind.test', $starter, $thisMonth->copy()->day(min(12, now()->day)), 'mid_start'],
            ['Frost Logistics', 'frost@northwind.test', $growth, $lastMonth->copy()->day(2), 'upgrade'],
            ['Glacier Legal', 'glacier@northwind.test', $growth, $lastMonth->copy()->day(1), 'churn'],
            ['Harbor Finance', 'harbor@northwind.test', $starter, $lastMonth->copy()->day(1), 'churn'],
            ['Iris Education', 'iris@northwind.test', $starter, $lastMonth->copy()->day(1), 'low'],
            ['Juniper Retail', 'juniper@northwind.test', $growth, $thisMonth->copy()->day(min(4, now()->day)), 'new'],
        ];

        $customers = [];

        foreach ($profiles as [$name, $email, $plan, $start, $pattern]) {
            $customer = Customer::query()->create([
                'merchant_id' => $merchant->id,
                'name' => $name,
                'email' => $email,
            ]);
            $subscriptions->subscribe($customer, $plan, $start);
            $customers[$pattern === 'upgrade' || $pattern === 'churn' ? $name : $pattern.$name] = [$customer, $pattern, $start];

            $this->seedUsage($merchant, $customer, $pattern, $start);
        }

        $frost = Customer::query()->where('email', 'frost@northwind.test')->first();
        $upgradeDay = $thisMonth->copy()->day(min(12, max(2, now()->day)));
        $subscriptions->changePlan($frost, $scale, $upgradeDay);

        app(InvoiceService::class)->generateForPeriod(
            $lastMonth->copy(),
            $lastMonth->copy()->endOfMonth()->startOfDay(),
            BillingCycle::Monthly,
            $merchant->id,
        );
    }

    private function seedUsage(Merchant $merchant, Customer $customer, string $pattern, Carbon $subscribedAt): void
    {
        $cursor = now()->copy()->startOfMonth()->subMonth();
        $end = now()->copy()->startOfDay();

        while ($cursor->lte($end)) {
            if ($cursor->lt($subscribedAt->copy()->startOfDay())) {
                $cursor->addDay();
                continue;
            }

            $isLastMonth = $cursor->month !== now()->month;
            $units = match ($pattern) {
                'high' => $isLastMonth ? 4200 : 4800,
                'overage' => $isLastMonth ? 900 : 1100,
                'mid_start' => 400,
                'upgrade' => $cursor->day < 12 ? 2200 : 3500,
                'churn' => $isLastMonth ? 3000 : 800,
                'low' => 120,
                'new' => 1500,
                default => 200,
            };

            $jitter = (int) floor($units * (($cursor->day % 5) - 2) * 0.04);
            $units = max(1, $units + $jitter);

            UsageEvent::query()->create([
                'merchant_id' => $merchant->id,
                'customer_id' => $customer->id,
                'units' => $units,
                'usage_date' => $cursor->toDateString(),
                'idempotency_key' => (string) Str::uuid(),
                'created_at' => $cursor->copy()->setTime(18, 0),
            ]);

            UsageDailyAggregate::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'usage_date' => $cursor->toDateString(),
                ],
                [
                    'merchant_id' => $merchant->id,
                    'total_units' => $units,
                ],
            );

            $cursor->addDay();
        }
    }
}

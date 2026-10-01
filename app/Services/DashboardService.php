<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\UsageDailyAggregate;
use App\Support\BillingPeriod;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function __construct(
        private InvoiceService $invoices,
        private BillingCalculator $calculator,
    ) {}

    public function forMerchant(Merchant $merchant, ?CarbonInterface $asOf = null): array
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        [$cycleStart, $cycleEnd] = BillingPeriod::containing($asOf, BillingCycle::Monthly);

        $ttl = (int) config('billing.dashboard_cache_ttl_seconds');
        $key = "dashboard.merchant.{$merchant->id}.{$cycleStart->toDateString()}";

        return Cache::remember($key, $ttl, function () use ($merchant, $asOf, $cycleStart, $cycleEnd) {
            return $this->build($merchant, $asOf, $cycleStart, $cycleEnd);
        });
    }

    public function forget(Merchant $merchant): void
    {
        [$start] = BillingPeriod::containing(now(), BillingCycle::Monthly);
        Cache::forget("dashboard.merchant.{$merchant->id}.{$start->toDateString()}");
    }

    private function build(
        Merchant $merchant,
        CarbonInterface $asOf,
        CarbonInterface $cycleStart,
        CarbonInterface $cycleEnd,
    ): array {
        $monthUsage = UsageDailyAggregate::query()
            ->selectRaw('customer_id, SUM(total_units) as units')
            ->where('merchant_id', $merchant->id)
            ->whereBetween('usage_date', [$cycleStart->toDateString(), $asOf->toDateString()])
            ->groupBy('customer_id')
            ->pluck('units', 'customer_id');

        $topIds = $monthUsage->sortDesc()->take(5)->keys();
        $customers = Customer::query()
            ->whereIn('id', $topIds)
            ->with('activeSubscription.plan')
            ->get()
            ->keyBy('id');

        $topCustomers = $topIds->map(function ($id) use ($customers, $monthUsage) {
            $customer = $customers->get($id);
            if (! $customer) {
                return null;
            }

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'plan' => $customer->activeSubscription?->plan?->name,
                'units' => (int) $monthUsage[$id],
            ];
        })->filter()->values();

        $projectedOverage = $this->projectedOverageRevenue($merchant, $asOf, $cycleStart, $cycleEnd);
        $churnRisk = $this->churnRisk($merchant, $asOf, $cycleStart);

        return [
            'merchant' => [
                'id' => $merchant->id,
                'name' => $merchant->name,
            ],
            'cycle' => [
                'start' => $cycleStart->toDateString(),
                'end' => $cycleEnd->toDateString(),
                'as_of' => $asOf->toDateString(),
            ],
            'kpis' => [
                'total_usage_this_month' => (int) $monthUsage->sum(),
                'active_customers' => Subscription::query()
                    ->where('merchant_id', $merchant->id)
                    ->where('status', 'active')
                    ->count(),
                'projected_overage_revenue' => $projectedOverage['total'],
                'churn_risk_count' => $churnRisk->count(),
            ],
            'top_customers' => $topCustomers,
            'projected_overage_revenue' => $projectedOverage,
            'churn_risk' => $churnRisk,
        ];
    }

    private function projectedOverageRevenue(
        Merchant $merchant,
        CarbonInterface $asOf,
        CarbonInterface $cycleStart,
        CarbonInterface $cycleEnd,
    ): array {
        $total = '0.0000';
        $rows = [];

        Subscription::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', 'active')
            ->with('customer')
            ->orderBy('id')
            ->chunkById(200, function ($subscriptions) use ($cycleStart, $cycleEnd, $asOf, &$total, &$rows) {
                foreach ($subscriptions as $subscription) {
                    $segments = $this->invoices->cycleSegments($subscription->customer, $cycleStart, $cycleEnd);

                    if ($segments === []) {
                        continue;
                    }

                    $projected = [];
                    foreach ($segments as $segment) {
                        if ($segment->start->gt($asOf)) {
                            continue;
                        }

                        $segmentDays = BillingPeriod::inclusiveDays($segment->start, $segment->end);
                        $elapsedEnd = $asOf->copy()->startOfDay()->min($segment->end);
                        $elapsedInSeg = max(1, BillingPeriod::inclusiveDays($segment->start, $elapsedEnd));
                        $complete = $segment->end->lte($asOf);

                        $projectedUnits = $complete
                            ? $segment->usageUnits
                            : (int) round((float) Money::mul(
                                (string) $segment->usageUnits,
                                Money::div((string) $segmentDays, (string) $elapsedInSeg, 8),
                                4,
                            ));

                        $projected[] = new \App\DTOs\CycleSegment(
                            planId: $segment->planId,
                            planName: $segment->planName,
                            basePrice: $segment->basePrice,
                            includedUnits: $segment->includedUnits,
                            overageRate: $segment->overageRate,
                            start: $segment->start,
                            end: $segment->end,
                            usageUnits: $projectedUnits,
                        );
                    }

                    $calc = $this->calculator->calculate($cycleStart, $cycleEnd, $projected);

                    if (bccomp($calc->overageAmount, '0', 4) === 1) {
                        $total = Money::add($total, $calc->overageAmount);
                        $rows[] = [
                            'customer_id' => $subscription->customer_id,
                            'customer' => $subscription->customer->name,
                            'overage' => $calc->overageAmount,
                        ];
                    }
                }
            });

        usort($rows, fn ($a, $b) => bccomp($b['overage'], $a['overage'], 4));

        return [
            'total' => $total,
            'customers' => array_slice($rows, 0, 10),
        ];
    }

    private function churnRisk(Merchant $merchant, CarbonInterface $asOf, CarbonInterface $thisStart): Collection
    {
        $lastStart = $thisStart->copy()->subMonth()->startOfMonth();
        $lastEnd = $thisStart->copy()->subMonth()->endOfMonth()->startOfDay();
        $comparableLastEnd = $lastStart->copy()->addDays(BillingPeriod::inclusiveDays($thisStart, $asOf) - 1);
        if ($comparableLastEnd->gt($lastEnd)) {
            $comparableLastEnd = $lastEnd;
        }

        $thisMonth = UsageDailyAggregate::query()
            ->selectRaw('customer_id, SUM(total_units) as units')
            ->where('merchant_id', $merchant->id)
            ->whereBetween('usage_date', [$thisStart->toDateString(), $asOf->toDateString()])
            ->groupBy('customer_id')
            ->pluck('units', 'customer_id');

        $lastMonth = UsageDailyAggregate::query()
            ->selectRaw('customer_id, SUM(total_units) as units')
            ->where('merchant_id', $merchant->id)
            ->whereBetween('usage_date', [$lastStart->toDateString(), $comparableLastEnd->toDateString()])
            ->groupBy('customer_id')
            ->pluck('units', 'customer_id');

        $atRiskIds = $lastMonth->filter(function ($lastUnits, $customerId) use ($thisMonth) {
            $lastUnits = (int) $lastUnits;
            if ($lastUnits <= 0) {
                return false;
            }
            $current = (int) ($thisMonth[$customerId] ?? 0);

            return $current <= ($lastUnits * 0.5);
        })->keys();

        $customers = Customer::query()->whereIn('id', $atRiskIds)->get()->keyBy('id');

        return $atRiskIds->map(function ($id) use ($customers, $thisMonth, $lastMonth) {
            $customer = $customers->get($id);
            if (! $customer) {
                return null;
            }
            $last = (int) $lastMonth[$id];
            $current = (int) ($thisMonth[$id] ?? 0);
            $drop = $last > 0 ? round((($last - $current) / $last) * 100, 1) : 0;

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'last_period_units' => $last,
                'this_period_units' => $current,
                'drop_percent' => $drop,
            ];
        })->filter()->sortByDesc('drop_percent')->values();
    }
}

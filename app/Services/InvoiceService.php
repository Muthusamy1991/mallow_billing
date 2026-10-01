<?php

namespace App\Services;

use App\DTOs\CycleSegment;
use App\Enums\BillingCycle;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use App\Models\UsageDailyAggregate;
use App\Support\BillingPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService
{
    public function __construct(private BillingCalculator $calculator) {}

    public function generateForEndedCycles(CarbonInterface $asOf): int
    {
        $asOf = $asOf->copy()->startOfDay();
        $generated = 0;

        $yesterday = $asOf->copy()->subDay();

        if ($yesterday->isLastOfMonth()) {
            $generated += $this->generateForPeriod(
                $yesterday->copy()->startOfMonth(),
                $yesterday->copy()->endOfMonth()->startOfDay(),
                BillingCycle::Monthly,
            );
        }

        if ($yesterday->month === 12 && $yesterday->day === 31) {
            $generated += $this->generateForPeriod(
                $yesterday->copy()->startOfYear(),
                $yesterday,
                BillingCycle::Yearly,
            );
        }

        return $generated;
    }

    public function generateForPeriod(
        CarbonInterface $cycleStart,
        CarbonInterface $cycleEnd,
        BillingCycle $cycle,
        ?int $merchantId = null,
    ): int {
        $chunkSize = (int) config('billing.invoice_chunk_size');
        $generated = 0;

        Subscription::query()
            ->when($merchantId, fn ($q) => $q->where('merchant_id', $merchantId))
            ->where('billing_cycle', $cycle)
            ->whereDate('started_at', '<=', $cycleEnd->toDateString())
            ->where(function ($q) use ($cycleStart) {
                $q->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', $cycleStart->toDateString());
            })
            ->orderBy('id')
            ->chunkById($chunkSize, function ($subscriptions) use ($cycleStart, $cycleEnd, &$generated) {
                foreach ($subscriptions as $subscription) {
                    $invoice = $this->generateForSubscription($subscription, $cycleStart, $cycleEnd);
                    if ($invoice) {
                        $generated++;
                    }
                }
            });

        return $generated;
    }

    public function generateForSubscription(
        Subscription $subscription,
        CarbonInterface $cycleStart,
        CarbonInterface $cycleEnd,
    ): ?Invoice {
        $customer = $subscription->customer;
        $segments = $this->cycleSegments($customer, $cycleStart, $cycleEnd);

        if ($segments === []) {
            return null;
        }

        $calculation = $this->calculator->calculate($cycleStart, $cycleEnd, $segments);

        return DB::transaction(function () use ($subscription, $customer, $cycleStart, $cycleEnd, $calculation) {
            $invoice = Invoice::query()
                ->where('customer_id', $customer->id)
                ->whereDate('cycle_start', $cycleStart->toDateString())
                ->whereDate('cycle_end', $cycleEnd->toDateString())
                ->first();

            $payload = [
                'merchant_id' => $customer->merchant_id,
                'subscription_id' => $subscription->id,
                'base_amount' => $calculation->baseAmount,
                'overage_amount' => $calculation->overageAmount,
                'total_amount' => $calculation->totalAmount,
                'total_units' => $calculation->totalUnits,
                'status' => InvoiceStatus::Issued,
                'issued_at' => $invoice?->issued_at ?? now(),
            ];

            if ($invoice) {
                $invoice->update($payload);
            } else {
                $invoice = Invoice::query()->create(array_merge($payload, [
                    'customer_id' => $customer->id,
                    'cycle_start' => $cycleStart->toDateString(),
                    'cycle_end' => $cycleEnd->toDateString(),
                    'number' => $this->nextNumber($customer->merchant_id, $cycleStart),
                ]));
            }

            $invoice->lineItems()->delete();

            foreach ($calculation->lines as $line) {
                InvoiceLineItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'type' => $line->type,
                    'plan_id' => $line->planId,
                    'description' => $line->description,
                    'segment_start' => $line->segmentStart,
                    'segment_end' => $line->segmentEnd,
                    'segment_days' => $line->segmentDays,
                    'units' => $line->units,
                    'included_units' => $line->includedUnits,
                    'overage_units' => $line->overageUnits,
                    'amount' => $line->amount,
                ]);
            }

            return $invoice;
        });
    }

    /**
     * @return list<CycleSegment>
     */
    public function cycleSegments(Customer $customer, CarbonInterface $cycleStart, CarbonInterface $cycleEnd): array
    {
        $rows = SubscriptionSegment::query()
            ->where('customer_id', $customer->id)
            ->whereDate('started_at', '<=', $cycleEnd->toDateString())
            ->where(function ($q) use ($cycleStart) {
                $q->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', $cycleStart->toDateString());
            })
            ->orderBy('started_at')
            ->get();

        $segments = [];

        foreach ($rows as $row) {
            $clipped = BillingPeriod::clip($row->started_at, $row->ended_at, $cycleStart, $cycleEnd);

            if ($clipped === null) {
                continue;
            }

            [$start, $end] = $clipped;

            $usage = (int) UsageDailyAggregate::query()
                ->where('customer_id', $customer->id)
                ->whereBetween('usage_date', [$start->toDateString(), $end->toDateString()])
                ->sum('total_units');

            $segments[] = new CycleSegment(
                planId: $row->plan_id,
                planName: $row->plan_name,
                basePrice: (string) $row->base_price,
                includedUnits: (int) $row->included_units,
                overageRate: (string) $row->overage_rate,
                start: $start,
                end: $end,
                usageUnits: $usage,
            );
        }

        return $segments;
    }

    private function nextNumber(int $merchantId, CarbonInterface $cycleStart): string
    {
        return sprintf(
            'INV-%d-%s-%s',
            $merchantId,
            $cycleStart->format('Ym'),
            Str::upper(Str::random(6)),
        );
    }
}

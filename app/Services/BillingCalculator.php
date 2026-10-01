<?php

namespace App\Services;

use App\DTOs\CycleSegment;
use App\DTOs\InvoiceCalculation;
use App\DTOs\InvoiceLineDraft;
use App\Enums\InvoiceLineType;
use App\Support\BillingPeriod;
use App\Support\Money;
use Carbon\CarbonInterface;

class BillingCalculator
{
    /**
     * @param  list<CycleSegment>  $segments
     */
    public function calculate(
        CarbonInterface $cycleStart,
        CarbonInterface $cycleEnd,
        array $segments,
    ): InvoiceCalculation {
        $daysInCycle = BillingPeriod::inclusiveDays($cycleStart, $cycleEnd);
        $lines = [];
        $baseAmount = '0.0000';
        $overageAmount = '0.0000';
        $totalUnits = 0;

        foreach ($segments as $segment) {
            $segmentDays = BillingPeriod::inclusiveDays($segment->start, $segment->end);
            $factor = Money::div((string) $segmentDays, (string) $daysInCycle, 8);

            $proratedBase = Money::mul($segment->basePrice, $factor, 4);
            $proratedIncluded = (int) floor((float) Money::mul((string) $segment->includedUnits, $factor, 4));
            $overageUnits = max(0, $segment->usageUnits - $proratedIncluded);
            $segmentOverage = Money::mul((string) $overageUnits, $segment->overageRate, 4);

            $totalUnits += $segment->usageUnits;
            $baseAmount = Money::add($baseAmount, $proratedBase);
            $overageAmount = Money::add($overageAmount, $segmentOverage);

            $prorationNote = $segmentDays === $daysInCycle
                ? 'full cycle'
                : "{$segmentDays}/{$daysInCycle} days";

            $lines[] = new InvoiceLineDraft(
                type: InvoiceLineType::Base,
                planId: $segment->planId,
                planName: $segment->planName,
                description: "{$segment->planName} base ({$prorationNote})",
                segmentStart: $segment->start->toDateString(),
                segmentEnd: $segment->end->toDateString(),
                segmentDays: $segmentDays,
                units: $segment->usageUnits,
                includedUnits: $proratedIncluded,
                overageUnits: 0,
                amount: $proratedBase,
            );

            $lines[] = new InvoiceLineDraft(
                type: InvoiceLineType::Overage,
                planId: $segment->planId,
                planName: $segment->planName,
                description: "{$segment->planName} overage ({$overageUnits} units @ {$segment->overageRate})",
                segmentStart: $segment->start->toDateString(),
                segmentEnd: $segment->end->toDateString(),
                segmentDays: $segmentDays,
                units: $segment->usageUnits,
                includedUnits: $proratedIncluded,
                overageUnits: $overageUnits,
                amount: $segmentOverage,
            );
        }

        return new InvoiceCalculation(
            baseAmount: $baseAmount,
            overageAmount: $overageAmount,
            totalAmount: Money::add($baseAmount, $overageAmount),
            totalUnits: $totalUnits,
            lines: $lines,
        );
    }
}

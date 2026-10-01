<?php

namespace App\Support;

use App\Enums\BillingCycle;
use Carbon\CarbonInterface;

final class BillingPeriod
{
    /**
     * Calendar-aligned billing window that contains $date.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public static function containing(CarbonInterface $date, BillingCycle $cycle): array
    {
        $date = $date->copy()->startOfDay();

        return match ($cycle) {
            BillingCycle::Monthly => [
                $date->copy()->startOfMonth(),
                $date->copy()->endOfMonth()->startOfDay(),
            ],
            BillingCycle::Yearly => [
                $date->copy()->startOfYear(),
                $date->copy()->endOfYear()->startOfDay(),
            ],
        };
    }

    public static function inclusiveDays(CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}|null
     */
    public static function clip(
        CarbonInterface $windowStart,
        ?CarbonInterface $windowEnd,
        CarbonInterface $cycleStart,
        CarbonInterface $cycleEnd,
    ): ?array {
        $start = $windowStart->copy()->startOfDay()->max($cycleStart->copy()->startOfDay());
        $endSource = $windowEnd?->copy()->startOfDay() ?? $cycleEnd->copy()->startOfDay();
        $end = $endSource->min($cycleEnd->copy()->startOfDay());

        if ($start->gt($end)) {
            return null;
        }

        return [$start, $end];
    }
}

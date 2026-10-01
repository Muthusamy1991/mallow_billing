<?php

namespace App\DTOs;

use Carbon\CarbonInterface;

final class CycleSegment
{
    public function __construct(
        public int $planId,
        public string $planName,
        public string $basePrice,
        public int $includedUnits,
        public string $overageRate,
        public CarbonInterface $start,
        public CarbonInterface $end,
        public int $usageUnits,
    ) {}
}

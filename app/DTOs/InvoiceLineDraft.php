<?php

namespace App\DTOs;

use App\Enums\InvoiceLineType;

final class InvoiceLineDraft
{
    public function __construct(
        public InvoiceLineType $type,
        public int $planId,
        public string $planName,
        public string $description,
        public string $segmentStart,
        public string $segmentEnd,
        public int $segmentDays,
        public int $units,
        public int $includedUnits,
        public int $overageUnits,
        public string $amount,
    ) {}
}

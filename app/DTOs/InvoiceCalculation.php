<?php

namespace App\DTOs;

final class InvoiceCalculation
{
    /**
     * @param  list<InvoiceLineDraft>  $lines
     */
    public function __construct(
        public string $baseAmount,
        public string $overageAmount,
        public string $totalAmount,
        public int $totalUnits,
        public array $lines,
    ) {}
}

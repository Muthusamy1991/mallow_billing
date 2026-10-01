<?php

namespace App\Jobs;

use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateCycleInvoicesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public ?string $asOf = null) {}

    public function handle(InvoiceService $invoices): void
    {
        $asOf = $this->asOf ? Carbon::parse($this->asOf) : now();
        $invoices->generateForEndedCycles($asOf);
    }
}

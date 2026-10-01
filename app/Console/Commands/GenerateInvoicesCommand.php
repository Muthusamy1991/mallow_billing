<?php

namespace App\Console\Commands;

use App\Enums\BillingCycle;
use App\Jobs\GenerateCycleInvoicesJob;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateInvoicesCommand extends Command
{
    protected $signature = 'billing:invoices
        {--as-of= : Reference date for "cycle ended yesterday" logic}
        {--month= : Generate monthly invoices for Y-m instead of using the scheduler rule}
        {--sync : Run inline}';

    protected $description = 'Generate invoices for billing cycles that have ended.';

    public function handle(InvoiceService $invoices): int
    {
        if ($month = $this->option('month')) {
            $start = Carbon::parse($month.'-01')->startOfMonth();
            $end = $start->copy()->endOfMonth()->startOfDay();
            $count = $invoices->generateForPeriod($start, $end, BillingCycle::Monthly);
            $this->info("Generated/updated {$count} monthly invoice(s) for {$start->format('F Y')}.");

            return self::SUCCESS;
        }

        $asOf = $this->option('as-of');

        if ($this->option('sync')) {
            $count = $invoices->generateForEndedCycles($asOf ? Carbon::parse($asOf) : now());
            $this->info("Generated {$count} invoice(s).");
        } else {
            GenerateCycleInvoicesJob::dispatch($asOf);
            $this->info('Invoice job queued.');
        }

        return self::SUCCESS;
    }
}

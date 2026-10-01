<?php

namespace App\Console\Commands;

use App\Jobs\AggregateDailyUsageJob;
use Illuminate\Console\Command;

class AggregateUsageCommand extends Command
{
    protected $signature = 'billing:aggregate {date? : Date to rebuild (Y-m-d). Defaults to yesterday.} {--sync : Run inline instead of queueing}';

    protected $description = 'Chunked rebuild of daily usage aggregates from raw events.';

    public function handle(): int
    {
        $date = $this->argument('date');
        $job = new AggregateDailyUsageJob($date);

        if ($this->option('sync')) {
            $this->info('Aggregating inline...');
            $job->handle(app(\App\Services\DailyUsageAggregator::class));
        } else {
            AggregateDailyUsageJob::dispatch($date);
            $this->info('Aggregation job queued.');
        }

        return self::SUCCESS;
    }
}

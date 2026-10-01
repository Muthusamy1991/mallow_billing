<?php

namespace App\Jobs;

use App\Services\DailyUsageAggregator;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AggregateDailyUsageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public ?string $date = null) {}

    public function handle(DailyUsageAggregator $aggregator): void
    {
        $date = $this->date ? Carbon::parse($this->date) : now()->subDay();
        $aggregator->aggregateDate($date);
    }
}

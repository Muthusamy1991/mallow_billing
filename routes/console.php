<?php

use App\Jobs\AggregateDailyUsageJob;
use App\Jobs\GenerateCycleInvoicesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new AggregateDailyUsageJob)->dailyAt('00:15')->name('billing.aggregate-daily-usage');
Schedule::job(new GenerateCycleInvoicesJob)->dailyAt('00:45')->name('billing.generate-cycle-invoices');

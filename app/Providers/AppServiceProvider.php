<?php

namespace App\Providers;

use App\Models\Plan;
use App\Observers\PlanObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Plan::observe(PlanObserver::class);

        RateLimiter::for('usage', function (Request $request) {
            $key = $request->header('X-Api-Key') ?: $request->ip();

            return Limit::perMinute((int) config('billing.usage_rate_limit_per_minute'))
                ->by($key);
        });
    }
}

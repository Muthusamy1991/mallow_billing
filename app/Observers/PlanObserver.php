<?php

namespace App\Observers;

use App\Models\Plan;
use App\Services\PlanCache;

class PlanObserver
{
    public function __construct(private PlanCache $planCache) {}

    public function saved(Plan $plan): void
    {
        $this->planCache->forget($plan);
    }

    public function deleted(Plan $plan): void
    {
        $this->planCache->forget($plan);
    }
}

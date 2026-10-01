<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Facades\Cache;

class PlanCache
{
    public static function key(int $planId): string
    {
        return "plans.{$planId}";
    }

    public static function merchantIndexKey(int $merchantId): string
    {
        return "plans.merchant.{$merchantId}";
    }

    public function find(int $planId): ?Plan
    {
        return Cache::remember(
            self::key($planId),
            config('billing.plan_cache_ttl_seconds'),
            fn () => Plan::query()->find($planId),
        );
    }

    public function forMerchant(int $merchantId): \Illuminate\Support\Collection
    {
        return Cache::remember(
            self::merchantIndexKey($merchantId),
            config('billing.plan_cache_ttl_seconds'),
            fn () => Plan::query()
                ->where('merchant_id', $merchantId)
                ->where('is_active', true)
                ->orderBy('base_price')
                ->get(),
        );
    }

    public function forget(Plan $plan): void
    {
        Cache::forget(self::key($plan->id));
        Cache::forget(self::merchantIndexKey($plan->merchant_id));
    }
}

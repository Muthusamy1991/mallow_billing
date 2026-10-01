<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plan cache
    |--------------------------------------------------------------------------
    |
    | Pricing lookups are cached by plan id. Invalidate on any plan write
    | (see PlanObserver). Redis is the production store; database/file/array
    | are acceptable for this exercise.
    |
    */

    'plan_cache_ttl_seconds' => (int) env('BILLING_PLAN_CACHE_TTL', 3600),

    'dashboard_cache_ttl_seconds' => (int) env('BILLING_DASHBOARD_CACHE_TTL', 60),

    'usage_rate_limit_per_minute' => (int) env('BILLING_USAGE_RATE_LIMIT', 120),

    'aggregation_chunk_size' => (int) env('BILLING_AGGREGATION_CHUNK', 500),

    'invoice_chunk_size' => (int) env('BILLING_INVOICE_CHUNK', 200),

];

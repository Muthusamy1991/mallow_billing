<?php

namespace App\Models;

use App\Enums\BillingCycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionSegment extends Model
{
    protected $fillable = [
        'subscription_id',
        'merchant_id',
        'customer_id',
        'plan_id',
        'plan_name',
        'base_price',
        'included_units',
        'overage_rate',
        'billing_cycle',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:4',
            'overage_rate' => 'decimal:6',
            'included_units' => 'integer',
            'billing_cycle' => BillingCycle::class,
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public static function snapshotFromPlan(Plan $plan, array $attributes = []): array
    {
        return array_merge([
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'base_price' => $plan->base_price,
            'included_units' => $plan->included_units,
            'overage_rate' => $plan->overage_rate,
            'billing_cycle' => $plan->billing_cycle,
        ], $attributes);
    }
}

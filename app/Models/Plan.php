<?php

namespace App\Models;

use App\Enums\BillingCycle;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'name',
        'base_price',
        'billing_cycle',
        'included_units',
        'overage_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:4',
            'overage_rate' => 'decimal:6',
            'included_units' => 'integer',
            'is_active' => 'boolean',
            'billing_cycle' => BillingCycle::class,
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}

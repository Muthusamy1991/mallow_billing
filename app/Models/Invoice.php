<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'number',
        'merchant_id',
        'customer_id',
        'subscription_id',
        'cycle_start',
        'cycle_end',
        'base_amount',
        'overage_amount',
        'total_amount',
        'total_units',
        'status',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'cycle_start' => 'date',
            'cycle_end' => 'date',
            'base_amount' => 'decimal:4',
            'overage_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'total_units' => 'integer',
            'status' => InvoiceStatus::class,
            'issued_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }
}

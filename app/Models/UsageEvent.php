<?php

namespace App\Models;

use Database\Factories\UsageEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageEvent extends Model
{
    /** @use HasFactory<UsageEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'merchant_id',
        'customer_id',
        'units',
        'usage_date',
        'idempotency_key',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'units' => 'integer',
            'usage_date' => 'date',
            'created_at' => 'datetime',
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
}

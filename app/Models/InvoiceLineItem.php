<?php

namespace App\Models;

use App\Enums\InvoiceLineType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLineItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'type',
        'plan_id',
        'description',
        'segment_start',
        'segment_end',
        'segment_days',
        'units',
        'included_units',
        'overage_units',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'type' => InvoiceLineType::class,
            'segment_start' => 'date',
            'segment_end' => 'date',
            'segment_days' => 'integer',
            'units' => 'integer',
            'included_units' => 'integer',
            'overage_units' => 'integer',
            'amount' => 'decimal:4',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}

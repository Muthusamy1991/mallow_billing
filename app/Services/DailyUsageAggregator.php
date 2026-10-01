<?php

namespace App\Services;

use App\Models\UsageDailyAggregate;
use App\Models\UsageEvent;
use Carbon\CarbonInterface;

class DailyUsageAggregator
{
    /**
     * Rebuild daily rollups from raw events for one date, in customer-id chunks.
     * Safe to re-run: aggregates are upserted from SUM(events), not incremented.
     */
    public function aggregateDate(CarbonInterface $date, ?int $chunkSize = null): int
    {
        $chunkSize ??= (int) config('billing.aggregation_chunk_size');
        $dateString = $date->toDateString();
        $processed = 0;
        $lastId = 0;

        do {
            $customerIds = UsageEvent::query()
                ->whereDate('usage_date', $dateString)
                ->where('customer_id', '>', $lastId)
                ->distinct()
                ->orderBy('customer_id')
                ->limit($chunkSize)
                ->pluck('customer_id');

            if ($customerIds->isEmpty()) {
                break;
            }

            $lastId = (int) $customerIds->last();

            $rows = UsageEvent::query()
                ->selectRaw('customer_id, merchant_id, SUM(units) as total_units')
                ->whereDate('usage_date', $dateString)
                ->whereIn('customer_id', $customerIds)
                ->groupBy('customer_id', 'merchant_id')
                ->get();

            foreach ($rows as $row) {
                $aggregate = UsageDailyAggregate::query()
                    ->where('customer_id', $row->customer_id)
                    ->whereDate('usage_date', $dateString)
                    ->first();

                if ($aggregate) {
                    $aggregate->update([
                        'merchant_id' => $row->merchant_id,
                        'total_units' => (int) $row->total_units,
                    ]);
                } else {
                    UsageDailyAggregate::query()->create([
                        'customer_id' => $row->customer_id,
                        'usage_date' => $dateString,
                        'merchant_id' => $row->merchant_id,
                        'total_units' => (int) $row->total_units,
                    ]);
                }
                $processed++;
            }
        } while ($customerIds->count() === $chunkSize);

        return $processed;
    }
}

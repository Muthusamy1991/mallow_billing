<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\UsageDailyAggregate;
use App\Models\UsageEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class UsageRecorder
{
    /**
     * Insert a usage event idempotently. A retried request with the same
     * idempotency key returns the original row and does not double-count.
     *
     * @return array{event: UsageEvent, created: bool}
     */
    public function record(
        Merchant $merchant,
        Customer $customer,
        int $units,
        CarbonInterface $usageDate,
        string $idempotencyKey,
    ): array {
        try {
            $event = DB::transaction(function () use ($merchant, $customer, $units, $usageDate, $idempotencyKey) {
                $event = UsageEvent::query()->create([
                    'merchant_id' => $merchant->id,
                    'customer_id' => $customer->id,
                    'units' => $units,
                    'usage_date' => $usageDate->toDateString(),
                    'idempotency_key' => $idempotencyKey,
                    'created_at' => now(),
                ]);

                $this->bumpDailyAggregate($merchant->id, $customer->id, $usageDate, $units);

                return $event;
            });

            return ['event' => $event, 'created' => true];
        } catch (UniqueConstraintViolationException) {
            $event = UsageEvent::query()
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();

            return ['event' => $event, 'created' => false];
        }
    }

    private function bumpDailyAggregate(int $merchantId, int $customerId, CarbonInterface $usageDate, int $units): void
    {
        $date = $usageDate->toDateString();

        $updated = UsageDailyAggregate::query()
            ->where('customer_id', $customerId)
            ->whereDate('usage_date', $date)
            ->increment('total_units', $units);

        if ($updated > 0) {
            return;
        }

        try {
            UsageDailyAggregate::query()->create([
                'merchant_id' => $merchantId,
                'customer_id' => $customerId,
                'usage_date' => $date,
                'total_units' => $units,
            ]);
        } catch (UniqueConstraintViolationException) {
            UsageDailyAggregate::query()
                ->where('customer_id', $customerId)
                ->whereDate('usage_date', $date)
                ->increment('total_units', $units);
        }
    }
}

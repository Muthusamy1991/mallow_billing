<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubscriptionService
{
    public function subscribe(Customer $customer, Plan $plan, CarbonInterface $startedAt): Subscription
    {
        if ($plan->merchant_id !== $customer->merchant_id) {
            throw new InvalidArgumentException('Plan does not belong to the customer merchant.');
        }

        if ($customer->activeSubscription()->exists()) {
            throw new InvalidArgumentException('Customer already has an active subscription.');
        }

        return DB::transaction(function () use ($customer, $plan, $startedAt) {
            $subscription = Subscription::query()->create([
                'merchant_id' => $customer->merchant_id,
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $plan->billing_cycle,
                'status' => SubscriptionStatus::Active,
                'started_at' => $startedAt->toDateString(),
            ]);

            SubscriptionSegment::query()->create(SubscriptionSegment::snapshotFromPlan($plan, [
                'subscription_id' => $subscription->id,
                'merchant_id' => $customer->merchant_id,
                'customer_id' => $customer->id,
                'started_at' => $startedAt->toDateString(),
                'ended_at' => null,
            ]));

            return $subscription;
        });
    }

    public function changePlan(Customer $customer, Plan $newPlan, CarbonInterface $effectiveDate): Subscription
    {
        $subscription = $customer->activeSubscription()->firstOrFail();

        if ($newPlan->merchant_id !== $customer->merchant_id) {
            throw new InvalidArgumentException('Plan does not belong to the customer merchant.');
        }

        if ($newPlan->billing_cycle !== $subscription->billing_cycle) {
            throw new InvalidArgumentException('Changing billing cycle mid-subscription is not supported. Cancel and resubscribe.');
        }

        if ($subscription->plan_id === $newPlan->id) {
            return $subscription;
        }

        $effective = $effectiveDate->copy()->startOfDay();

        if ($effective->lt($subscription->started_at)) {
            throw new InvalidArgumentException('Effective date cannot be before the subscription start.');
        }

        return DB::transaction(function () use ($subscription, $newPlan, $effective) {
            $open = $subscription->segments()->whereNull('ended_at')->lockForUpdate()->firstOrFail();

            if ($effective->lte($open->started_at)) {
                throw new InvalidArgumentException('Effective date must be after the current segment start.');
            }

            $open->update([
                'ended_at' => $effective->copy()->subDay()->toDateString(),
            ]);

            SubscriptionSegment::query()->create(SubscriptionSegment::snapshotFromPlan($newPlan, [
                'subscription_id' => $subscription->id,
                'merchant_id' => $subscription->merchant_id,
                'customer_id' => $subscription->customer_id,
                'started_at' => $effective->toDateString(),
                'ended_at' => null,
            ]));

            $subscription->update(['plan_id' => $newPlan->id]);

            return $subscription->refresh();
        });
    }
}

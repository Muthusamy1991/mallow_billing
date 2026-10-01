<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePlanRequest;
use App\Models\Customer;
use App\Models\Plan;
use App\Services\PlanCache;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class SubscriptionController extends Controller
{
    public function changePlan(
        ChangePlanRequest $request,
        Customer $customer,
        SubscriptionService $subscriptions,
        PlanCache $planCache,
    ): JsonResponse {
        $merchant = $request->attributes->get('merchant');

        if ($customer->merchant_id !== $merchant->id) {
            abort(404);
        }

        $plan = $planCache->find($request->integer('plan_id'));

        if (! $plan instanceof Plan) {
            abort(422, 'Unknown plan.');
        }

        try {
            $subscription = $subscriptions->changePlan(
                $customer,
                $plan,
                $request->date('effective_date'),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'ok',
            'subscription' => [
                'id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'started_at' => $subscription->started_at->toDateString(),
                'segments' => $subscription->segments()
                    ->orderBy('started_at')
                    ->get()
                    ->map(fn ($seg) => [
                        'plan_id' => $seg->plan_id,
                        'plan_name' => $seg->plan_name,
                        'started_at' => $seg->started_at->toDateString(),
                        'ended_at' => $seg->ended_at?->toDateString(),
                        'base_price' => $seg->base_price,
                        'included_units' => $seg->included_units,
                        'overage_rate' => $seg->overage_rate,
                    ]),
            ],
        ]);
    }
}

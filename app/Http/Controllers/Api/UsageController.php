<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUsageRequest;
use App\Models\Customer;
use App\Services\UsageRecorder;
use Illuminate\Http\JsonResponse;

class UsageController extends Controller
{
    public function store(StoreUsageRequest $request, UsageRecorder $recorder): JsonResponse
    {
        $merchant = $request->attributes->get('merchant');
        $customer = Customer::query()->findOrFail($request->integer('customer_id'));

        $result = $recorder->record(
            $merchant,
            $customer,
            $request->integer('units'),
            $request->date('usage_date'),
            $request->string('idempotency_key')->toString(),
        );

        return response()->json([
            'status' => 'ok',
            'idempotent_replay' => ! $result['created'],
            'usage' => [
                'id' => $result['event']->id,
                'customer_id' => $result['event']->customer_id,
                'units' => $result['event']->units,
                'usage_date' => $result['event']->usage_date->toDateString(),
                'idempotency_key' => $result['event']->idempotency_key,
            ],
        ], $result['created'] ? 201 : 200);
    }
}

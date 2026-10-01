<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class MerchantDashboardController extends Controller
{
    public function show(Merchant $merchant, DashboardService $dashboard): JsonResponse
    {
        return response()->json($dashboard->forMerchant($merchant));
    }
}

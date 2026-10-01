<?php

use App\Http\Controllers\Api\MerchantDashboardController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\UsageController;
use App\Http\Middleware\EnsureMerchantApiKey;
use Illuminate\Support\Facades\Route;

Route::post('/usage', [UsageController::class, 'store'])
    ->middleware(['merchant.api', 'throttle:usage'])
    ->name('api.usage.store');

Route::get('/merchants/{merchant}/dashboard', [MerchantDashboardController::class, 'show'])
    ->name('api.merchants.dashboard');

Route::post('/customers/{customer}/plan-change', [SubscriptionController::class, 'changePlan'])
    ->middleware(['merchant.api'])
    ->name('api.customers.plan-change');

<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('home');
Route::get('/merchants/{merchant}', [DashboardController::class, 'show'])->name('merchants.show');
Route::get('/merchants/{merchant}/customers/{customer}', [DashboardController::class, 'customer'])->name('merchants.customers.show');
Route::get('/merchants/{merchant}/invoices/{invoice}', [DashboardController::class, 'invoice'])->name('merchants.invoices.show');

Route::post('/merchants/{merchant}/usage', [DashboardController::class, 'recordUsage'])->name('merchants.usage.store');
Route::post('/merchants/{merchant}/customers/{customer}/plan-change', [DashboardController::class, 'changePlan'])->name('merchants.customers.plan-change');
Route::post('/merchants/{merchant}/jobs/aggregate', [DashboardController::class, 'runAggregation'])->name('merchants.jobs.aggregate');
Route::post('/merchants/{merchant}/jobs/invoices', [DashboardController::class, 'runInvoices'])->name('merchants.jobs.invoices');

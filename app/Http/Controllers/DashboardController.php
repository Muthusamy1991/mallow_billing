<?php

namespace App\Http\Controllers;

use App\Enums\BillingCycle;
use App\Jobs\AggregateDailyUsageJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Plan;
use App\Services\DashboardService;
use App\Services\InvoiceService;
use App\Services\PlanCache;
use App\Services\SubscriptionService;
use App\Services\UsageRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class DashboardController extends Controller
{
    public function index(): View
    {
        $merchants = Merchant::query()->withCount(['customers', 'plans'])->orderBy('name')->get();

        return view('merchants.index', compact('merchants'));
    }

    public function show(Merchant $merchant, DashboardService $dashboard): View
    {
        $data = $dashboard->forMerchant($merchant);
        $merchant->load(['customers' => fn ($q) => $q->orderBy('name')]);
        $invoices = Invoice::query()
            ->where('merchant_id', $merchant->id)
            ->with('customer')
            ->latest('cycle_start')
            ->limit(12)
            ->get();

        return view('merchants.dashboard', [
            'merchant' => $merchant,
            'dashboard' => $data,
            'invoices' => $invoices,
        ]);
    }

    public function customer(Merchant $merchant, Customer $customer, PlanCache $planCache): View
    {
        abort_unless($customer->merchant_id === $merchant->id, 404);

        $customer->load([
            'activeSubscription.plan',
            'segments',
            'invoices.lineItems',
            'dailyUsage' => fn ($q) => $q->orderByDesc('usage_date')->limit(14),
        ]);

        $plans = $planCache->forMerchant($merchant->id);

        return view('merchants.customer', compact('merchant', 'customer', 'plans'));
    }

    public function invoice(Merchant $merchant, Invoice $invoice): View
    {
        abort_unless($invoice->merchant_id === $merchant->id, 404);

        $invoice->load(['customer', 'lineItems']);

        return view('merchants.invoice', compact('merchant', 'invoice'));
    }

    public function recordUsage(Request $request, Merchant $merchant, UsageRecorder $recorder): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'units' => ['required', 'integer', 'min:1', 'max:1000000'],
            'usage_date' => ['required', 'date'],
        ]);

        $customer = Customer::query()->findOrFail($data['customer_id']);
        abort_unless($customer->merchant_id === $merchant->id, 404);

        $recorder->record(
            $merchant,
            $customer,
            (int) $data['units'],
            \Carbon\Carbon::parse($data['usage_date']),
            (string) Str::uuid(),
        );

        return back()->with('status', 'Usage event recorded.');
    }

    public function changePlan(
        Request $request,
        Merchant $merchant,
        Customer $customer,
        SubscriptionService $subscriptions,
        PlanCache $planCache,
    ): RedirectResponse {
        abort_unless($customer->merchant_id === $merchant->id, 404);

        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
            'effective_date' => ['required', 'date'],
        ]);

        $plan = $planCache->find((int) $data['plan_id']);
        abort_unless($plan instanceof Plan && $plan->merchant_id === $merchant->id, 404);

        try {
            $subscriptions->changePlan($customer, $plan, \Carbon\Carbon::parse($data['effective_date']));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['plan_id' => $e->getMessage()]);
        }

        return back()->with('status', 'Plan changed. Existing usage stays on the previous segment rate.');
    }

    public function runAggregation(Request $request): RedirectResponse
    {
        $date = $request->input('date', now()->toDateString());
        AggregateDailyUsageJob::dispatchSync($date);

        return back()->with('status', "Aggregated daily usage for {$date}.");
    }

    public function runInvoices(Request $request, Merchant $merchant, InvoiceService $invoices): RedirectResponse
    {
        $month = $request->input('month', now()->subMonth()->format('Y-m'));
        $start = \Carbon\Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth()->startOfDay();

        $count = $invoices->generateForPeriod($start, $end, BillingCycle::Monthly, $merchant->id);

        return back()->with('status', "Generated/updated {$count} invoice(s) for {$start->format('F Y')}.");
    }
}

@extends('layouts.app')

@section('title', $customer->name)

@section('content')
    <div class="hero">
        <div>
            <p class="muted"><a href="{{ route('merchants.show', $merchant) }}">← {{ $merchant->name }}</a></p>
            <h1>{{ $customer->name }}</h1>
            <p>{{ $customer->email }} · current plan
                <span class="pill">{{ $customer->activeSubscription?->plan?->name ?? 'None' }}</span>
            </p>
        </div>
    </div>

    <section class="grid-2">
        <article class="card">
            <div class="section-title">
                <h2>Plan change</h2>
                <span>Mid-cycle upgrade/downgrade</span>
            </div>
            <form class="stack" method="post" action="{{ route('merchants.customers.plan-change', [$merchant, $customer]) }}">
                @csrf
                <label>
                    New plan
                    <select name="plan_id" style="width:100%; margin-top:6px">
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected($customer->activeSubscription?->plan_id === $plan->id)>
                                {{ $plan->name }} · ${{ \App\Support\Money::format($plan->base_price) }}/{{ $plan->billing_cycle->value }} · {{ number_format($plan->included_units) }} included
                            </option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Effective date
                    <input type="date" name="effective_date" value="{{ now()->toDateString() }}" style="width:100%; margin-top:6px">
                </label>
                <button type="submit">Change plan</button>
            </form>
            <p class="muted">Usage before this date stays on the previous plan snapshot. After it, the new rate applies. Base price is prorated per segment.</p>
        </article>

        <article class="card">
            <div class="section-title"><h2>Segments</h2></div>
            <table>
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Window</th>
                        <th class="num">Included</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customer->segments->sortBy('started_at') as $segment)
                        <tr>
                            <td>{{ $segment->plan_name }}</td>
                            <td>{{ $segment->started_at->toDateString() }} → {{ $segment->ended_at?->toDateString() ?? 'open' }}</td>
                            <td class="num">{{ number_format($segment->included_units) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </article>
    </section>

    <section class="card" style="margin-top:16px">
        <div class="section-title"><h2>Recent daily usage</h2></div>
        <table>
            <thead>
                <tr><th>Date</th><th class="num">Units</th></tr>
            </thead>
            <tbody>
                @foreach ($customer->dailyUsage as $day)
                    <tr>
                        <td>{{ $day->usage_date->toDateString() }}</td>
                        <td class="num">{{ number_format($day->total_units) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="card" style="margin-top:16px">
        <div class="section-title"><h2>Invoices</h2></div>
        <table>
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Cycle</th>
                    <th class="num">Base</th>
                    <th class="num">Overage</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customer->invoices as $invoice)
                    <tr>
                        <td><a href="{{ route('merchants.invoices.show', [$merchant, $invoice]) }}">{{ $invoice->number }}</a></td>
                        <td>{{ $invoice->cycle_start->toDateString() }} → {{ $invoice->cycle_end->toDateString() }}</td>
                        <td class="num">${{ \App\Support\Money::format($invoice->base_amount) }}</td>
                        <td class="num">${{ \App\Support\Money::format($invoice->overage_amount) }}</td>
                        <td class="num">${{ \App\Support\Money::format($invoice->total_amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No invoices yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection

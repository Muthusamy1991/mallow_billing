@extends('layouts.app')

@section('title', $merchant->name)

@section('content')
    <div class="hero">
        <div>
            <h1>{{ $merchant->name }}</h1>
            <p>
                Cycle {{ $dashboard['cycle']['start'] }} → {{ $dashboard['cycle']['end'] }}
                · as of {{ $dashboard['cycle']['as_of'] }}
            </p>
        </div>
        <div class="toolbar">
            <form class="inline" method="post" action="{{ route('merchants.jobs.aggregate', $merchant) }}">
                @csrf
                <input type="date" name="date" value="{{ now()->toDateString() }}">
                <button class="secondary" type="submit">Rebuild daily aggregates</button>
            </form>
            <form class="inline" method="post" action="{{ route('merchants.jobs.invoices', $merchant) }}">
                @csrf
                <input type="month" name="month" value="{{ now()->subMonth()->format('Y-m') }}">
                <button type="submit">Generate cycle invoices</button>
            </form>
        </div>
    </div>

    <section class="kpis">
        <article class="card kpi">
            <div class="label">Usage this month</div>
            <div class="value">{{ number_format($dashboard['kpis']['total_usage_this_month']) }}</div>
            <div class="hint">Units from daily aggregates</div>
        </article>
        <article class="card kpi">
            <div class="label">Projected overage</div>
            <div class="value">${{ \App\Support\Money::format($dashboard['kpis']['projected_overage_revenue']) }}</div>
            <div class="hint">Pace-based, current cycle</div>
        </article>
        <article class="card kpi">
            <div class="label">Churn risk</div>
            <div class="value">{{ $dashboard['kpis']['churn_risk_count'] }}</div>
            <div class="hint">Usage down &gt;50% vs last month</div>
        </article>
        <article class="card kpi">
            <div class="label">Active customers</div>
            <div class="value">{{ $dashboard['kpis']['active_customers'] }}</div>
            <div class="hint">Live subscriptions</div>
        </article>
    </section>

    <section class="grid-2">
        <article class="card">
            <div class="section-title">
                <h2>Top 5 customers by usage</h2>
                <span>This month</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Plan</th>
                        <th class="num">Units</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dashboard['top_customers'] as $row)
                        <tr>
                            <td><a href="{{ route('merchants.customers.show', [$merchant, $row['id']]) }}">{{ $row['name'] }}</a></td>
                            <td><span class="pill">{{ $row['plan'] ?? '—' }}</span></td>
                            <td class="num">{{ number_format($row['units']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">No usage yet this month.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </article>

        <article class="card">
            <div class="section-title">
                <h2>Churn risk</h2>
                <span>Same-period comparison</span>
            </div>
            <div class="list">
                @forelse ($dashboard['churn_risk'] as $row)
                    <div class="risk">
                        <div>
                            <a href="{{ route('merchants.customers.show', [$merchant, $row['id']]) }}">{{ $row['name'] }}</a>
                            <div class="muted">{{ number_format($row['last_period_units']) }} → {{ number_format($row['this_period_units']) }}</div>
                        </div>
                        <span class="pill pill-warn">-{{ $row['drop_percent'] }}%</span>
                    </div>
                @empty
                    <p class="muted">No customers dropped more than 50%.</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="card" style="margin-top:16px">
        <div class="section-title">
            <h2>Record usage event</h2>
            <span>POST /api/usage equivalent</span>
        </div>
        <form class="inline" method="post" action="{{ route('merchants.usage.store', $merchant) }}">
            @csrf
            <select name="customer_id" required>
                @foreach ($merchant->customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->name }} (#{{ $customer->id }})</option>
                @endforeach
            </select>
            <input type="number" name="units" min="1" value="250" required>
            <input type="date" name="usage_date" value="{{ now()->toDateString() }}" required>
            <button type="submit">Record</button>
        </form>
        <p class="muted" style="margin-top:10px">
            API: <code>POST /api/usage</code> with header <code>X-Api-Key: {{ $merchant->api_key }}</code>
            and JSON <code>customer_id, units, usage_date, idempotency_key</code>.
        </p>
    </section>

    <section class="card" style="margin-top:16px">
        <div class="section-title">
            <h2>Recent invoices</h2>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Customer</th>
                    <th>Cycle</th>
                    <th class="num">Units</th>
                    <th class="num">Overage</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td><a href="{{ route('merchants.invoices.show', [$merchant, $invoice]) }}">{{ $invoice->number }}</a></td>
                        <td>{{ $invoice->customer->name }}</td>
                        <td>{{ $invoice->cycle_start->toDateString() }} → {{ $invoice->cycle_end->toDateString() }}</td>
                        <td class="num">{{ number_format($invoice->total_units) }}</td>
                        <td class="num">${{ \App\Support\Money::format($invoice->overage_amount) }}</td>
                        <td class="num">${{ \App\Support\Money::format($invoice->total_amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">No invoices yet. Generate last month's cycle above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection

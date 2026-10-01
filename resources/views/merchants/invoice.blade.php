@extends('layouts.app')

@section('title', $invoice->number)

@section('content')
    <div class="hero">
        <div>
            <p class="muted"><a href="{{ route('merchants.show', $merchant) }}">← {{ $merchant->name }}</a></p>
            <h1>{{ $invoice->number }}</h1>
            <p>
                {{ $invoice->customer->name }} ·
                {{ $invoice->cycle_start->toDateString() }} → {{ $invoice->cycle_end->toDateString() }} ·
                {{ $invoice->status->value }}
            </p>
        </div>
        <div>
            <div class="kpi">
                <div class="label">Total</div>
                <div class="value">${{ \App\Support\Money::format($invoice->total_amount) }}</div>
            </div>
        </div>
    </div>

    <section class="card">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Segment</th>
                    <th class="num">Units</th>
                    <th class="num">Included</th>
                    <th class="num">Overage</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->lineItems as $line)
                    <tr>
                        <td><span class="pill">{{ $line->type->value }}</span></td>
                        <td>{{ $line->description }}</td>
                        <td>{{ $line->segment_start?->toDateString() }} → {{ $line->segment_end?->toDateString() }}</td>
                        <td class="num">{{ number_format($line->units) }}</td>
                        <td class="num">{{ number_format($line->included_units) }}</td>
                        <td class="num">{{ number_format($line->overage_units) }}</td>
                        <td class="num amount">${{ \App\Support\Money::format($line->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="6">Base</th>
                    <th class="num">${{ \App\Support\Money::format($invoice->base_amount) }}</th>
                </tr>
                <tr>
                    <th colspan="6">Overage</th>
                    <th class="num">${{ \App\Support\Money::format($invoice->overage_amount) }}</th>
                </tr>
                <tr>
                    <th colspan="6">Total</th>
                    <th class="num">${{ \App\Support\Money::format($invoice->total_amount) }}</th>
                </tr>
            </tfoot>
        </table>
    </section>
@endsection

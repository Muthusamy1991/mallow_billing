@extends('layouts.app')

@section('title', 'Merchants')

@section('content')
    <div class="hero">
        <div>
            <h1>Merchants</h1>
            <p>Multi-tenant subscription billing and usage metering.</p>
        </div>
    </div>

    <div class="merchant-grid">
        @forelse ($merchants as $merchant)
            <a class="card merchant-card" href="{{ route('merchants.show', $merchant) }}" style="color: inherit; text-decoration: none;">
                <h2>{{ $merchant->name }}</h2>
                <p class="muted">{{ $merchant->email }}</p>
                <p>{{ $merchant->customers_count }} customers · {{ $merchant->plans_count }} plans</p>
                <p class="muted">API key for demo: <code>demo-northwind-key</code></p>
            </a>
        @empty
            <div class="card">No merchants yet. Run <code>php artisan migrate:fresh --seed</code>.</div>
        @endforelse
    </div>
@endsection

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Merchant dashboard') — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ route('home') }}">
            <span class="mark">MB</span>
            <span>
                <strong>Mallow Billing</strong>
                <small>Usage metering</small>
            </span>
        </a>
        <nav>
            <a href="{{ route('home') }}">Merchants</a>
            @isset($merchant)
                <a href="{{ route('merchants.show', $merchant) }}">Dashboard</a>
            @endisset
        </nav>
    </header>

    <main class="page">
        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="flash flash-error">{{ $errors->first() }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>

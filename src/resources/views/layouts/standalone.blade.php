{{-- The package's own minimal page, for apps that set no myfinance2.layout: a navbar with the Finance menu and the
     MyFinance2 page below it. Its sections are the defaults of myfinance2.layout_sections; the package's assets,
     jQuery and Bootstrap included, come in through 'styles' (partials/assets). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') | @endif{{ config('app.name') }}</title>
    @yield('styles')
</head>
<body>
    <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">{{ config('app.name') }}</a>
            <ul class="navbar-nav me-auto">
                @include('myfinance2::partials.nav')
            </ul>
            @auth
                <span class="navbar-text">{{ auth()->user()->name }}</span>
            @endauth
        </div>
    </nav>
    <main>
        @yield('content')
    </main>
    @yield('scripts')
</body>
</html>

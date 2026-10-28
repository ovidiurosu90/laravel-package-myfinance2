{{-- The package's prebuilt JS / CSS (README, "Assets") for the page's <head>: myfinance2-base first when the page has
     no jQuery / Bootstrap of its own (the standalone layout, or myfinance2.base_assets), then myfinance2. Deferred
     scripts run in document order, after the app's bundle and before the pages' inline module scripts. --}}
@php
    $bundles = config('myfinance2.layout') && !config('myfinance2.base_assets')
        ? ['myfinance2']
        : ['myfinance2-base', 'myfinance2'];
@endphp
@foreach ($bundles as $bundle)
    @foreach (['css', 'js'] as $type)
        @php
            $path = "vendor/myfinance2/{$bundle}.{$type}";
        @endphp
        @if (!file_exists(public_path($path)))
            <!-- MyFinance2: {{ $path }} is missing; php artisan vendor:publish --tag=myfinance2-assets --force -->
        @elseif ($type === 'css')
            <link rel="stylesheet" href="{{ asset($path) }}?v={{ filemtime(public_path($path)) }}">
        @else
            <script defer src="{{ asset($path) }}?v={{ filemtime(public_path($path)) }}"></script>
        @endif
    @endforeach
@endforeach

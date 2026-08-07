@php($staleQuotes = $staleQuotes ?? [])
@if(!empty($staleQuotes))
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    <h6 class="alert-heading mb-2">
        <i class="fa fa-exclamation-triangle me-1"></i>
        Stale market data ({{ count($staleQuotes) }})
        <small class="fw-normal ms-1">
            prices below may not reflect the current market; verify before acting on them
        </small>
    </h6>
    <ul class="mb-0 small">
        @foreach($staleQuotes as $alert)
        <li>
            <strong>{{ $alert['market_label'] }}</strong>
            is open but the latest price is {{ $alert['age_human'] }} old
            (last update {{ $alert['last_update_formatted'] }};
            no update in over {{ $alert['threshold_human'] }};
            <span class="text-decoration-underline"
                data-bs-toggle="tooltip" data-bs-custom-class="tooltip-md"
                data-bs-title="{{ implode(', ', $alert['symbols']) }}">{{ $alert['symbol_count'] }} {{ \Illuminate\Support\Str::plural('symbol', $alert['symbol_count']) }}</span>)
            @if(!empty($alert['delayed_feed']))
            <span class="text-muted">
                Yahoo Finance serves this exchange with a built-in
                {{ $alert['delayed_feed_human'] }} delay, so the threshold here is wider.
            </span>
            @endif
        </li>
        @endforeach
    </ul>
</div>
<div class="clearfix mb-3"></div>
@endif

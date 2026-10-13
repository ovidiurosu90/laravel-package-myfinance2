{{-- Active view: the range-bound, weak-tier-in-profit and tier signal symbols that are not enabled, so they raise no
     TRIM / ADD / BUY, TAKE PROFIT or tier signal alert (the Active list shows enabled symbols only, so they are easy
     to miss). $rangeNotEnabled is a list of ['symbol', 'range_bound', 'take_profit', 'tier_signal'] from the
     controller, as of the last hourly run (tier_signal only when it can alert). --}}
@if(!empty($rangeNotEnabled))
@php
    $count  = count($rangeNotEnabled);
    $kinds  = collect($rangeNotEnabled);
    $hasTp  = $kinds->contains(fn ($entry) => !empty($entry['take_profit']));
    $hasRb  = $kinds->contains(fn ($entry) => !empty($entry['range_bound']));
    $hasTs  = $kinds->contains(fn ($entry) => !empty($entry['tier_signal']));
    $what   = implode(' or ', array_filter([
        $hasRb ? 'range-bound' : null,
        $hasTp ? 'weak-tier-in-profit' : null,
        $hasTs ? 'tier signal' : null,
    ]));
    $alerts = implode(' or ', array_filter([
        $hasRb ? 'TRIM / ADD / BUY' : null,
        $hasTp ? 'TAKE PROFIT' : null,
        $hasTs ? 'tier signal' : null,
    ]));
@endphp
<div class="alert alert-info small py-2 mb-2 d-flex flex-wrap align-items-center gap-1">
    <span>
        <i class="fa fa-info-circle fa-xs me-1" aria-hidden="true"></i>
        {{ $count }} {{ $what }} {{ $count === 1 ? 'symbol is' : 'symbols are' }}
        not enabled, so {{ $count === 1 ? 'it' : 'they' }} cannot raise a {{ $alerts }} alert:
    </span>
    @foreach($rangeNotEnabled as $entry)
        <span class="text-nowrap fw-semibold">{{ $entry['symbol'] }}</span>
        @include('myfinance2::watchlistsymbols.tables.partials.range-bound-chip', [
            'range' => $entry['range_bound'] ?? null, 'compact' => true,
        ])
        @include('myfinance2::watchlistsymbols.tables.partials.take-profit-chip', [
            'takeProfit' => $entry['take_profit'] ?? null, 'compact' => true,
        ])
        @include('myfinance2::watchlistsymbols.tables.partials.tier-signal-chip', [
            'tierSignal' => $entry['tier_signal'] ?? null, 'compact' => true,
        ])
    @endforeach
    <span>
        Enable {{ $count === 1 ? 'it' : 'them' }} under
        <a href="{{ route('myfinance2::peak-proximity-alerts.index', ['view' => 'all']) }}">All</a>.
    </span>
</div>
@endif

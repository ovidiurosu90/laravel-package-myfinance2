{{--
    The trade form's tier reading (TradeTierReadingController), read-only: the /watchlist-symbols Tier and Tier History
    lines of the reading the trade is saved with, and one line with its verdict.
    $kept   the saved reading kept (the order's the trade fills, or the trade's own), or null
    $order  the order whose reading it is, or null
    $check  a new reading (live today, rebuilt for a past date) when nothing is kept, or null
    $trade  the trade being edited, or null
--}}
@php
    use ovidiuro\myfinance2\App\Models\TierSnapshot;

    $source   = $kept?->source ?? ($check['source'] ?? null);
    $reading  = $kept?->reading ?? ($check['reading'] ?? null);
    $warnings = $kept?->warnings ?? ($check['warnings'] ?? []);
    $verdict  = $kept?->verdict ?? ($check['verdict'] ?? null);
    $asOf     = $kept?->as_of_date?->format('Y-m-d') ?? ($check['as_of'] ?? null);
@endphp
@if($reading !== null)
<div class="border rounded px-3 py-2 mb-3">
    <div class="text-muted small mb-1">
        @if($kept !== null)
            <i class="fa fa-lock fa-fw" aria-hidden="true"></i>
            @if($order !== null)
                Tier reading copied from <a href="{{ route('myfinance2::orders.edit', $order->id) }}">order
                    #{{ $order->id }}</a>, saved {{ $kept->taken_at?->format('Y-m-d H:i') }}. Locked.
            @elseif($source === TierSnapshot::SOURCE_REBUILT)
                Tier reading rebuilt for {{ $asOf }} from the stored history, then locked.
            @elseif($source === TierSnapshot::SOURCE_LIVE)
                Tier reading saved with the trade, {{ $kept->taken_at?->format('Y-m-d H:i') }}. Locked.
            @endif
        @elseif($source === TierSnapshot::SOURCE_LIVE)
            Tier reading for {{ $symbol }}, live. Saved with the trade, then locked.
        @elseif($source === TierSnapshot::SOURCE_REBUILT)
            Tier reading rebuilt for {{ $asOf }} from the stored history (the trade's date). Saved with the trade,
            then locked.
        @endif
        @if($trade !== null && $kept === null)
            The symbol, action or date changed: a new decision, so a new reading.
        @endif
    </div>
    @if($source === TierSnapshot::SOURCE_NONE)
        <div class="text-muted fst-italic">{{ $reading['explanation'] ?? '' }}</div>
    @else
        @include('myfinance2::watchlistsymbols.tables.partials.tier-reading', [
            'reading' => $reading, 'idSuffix' => '-trade',
        ])
        @include('myfinance2::trades.partials.tier-verdict-line')
    @endif
</div>
@endif

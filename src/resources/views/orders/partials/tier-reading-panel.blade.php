{{-- The order's tier reading: live on a new decision (saved with the order on save, then locked), or the one saved
     with the order (a lock icon). Renders the /watchlist-symbols Tier and Tier History lines (tier-reading) with the
     share of the portfolio after the order and, on a saved reading, today's tier when it differs.
     $check, $kept, $now, $order, $symbol, $action: as in tier-reading. --}}
@php
    use ovidiuro\myfinance2\App\Services\MoneyFormat;
    use ovidiuro\myfinance2\App\Services\OrderTierWarnings;

    $reading = $check['reading'];
    $after   = $check['position_pct_after'] ?? null;
    $extra   = '';
    if ($after !== null && ($reading['portfolio'] ?? null) !== null) {
        $extra .= '<span class="text-muted text-nowrap">&rarr; ' . e(MoneyFormat::get_formatted_number_plain($after, 1))
            . '% after this order</span>';
    } elseif ($after !== null) {
        $extra .= '<span class="text-muted">&middot;</span> <span class="text-muted text-nowrap">'
            . e(MoneyFormat::get_formatted_number_plain($after, 1)) . '% of portfolio after this order</span>';
    }
    $nowTier = $now['reading']['effective_tier'] ?? null;
    if ($kept !== null && $nowTier !== null && $nowTier !== $kept->effective_tier) {
        $extra .= view('myfinance2::orders.partials.tier-now-chip', [
            'tier'    => $nowTier,
            'serious' => OrderTierWarnings::serious($now['warnings'] ?? []),
        ])->render();
    }
    $isNewDecision = $kept === null && $order !== null;
@endphp
<div class="border rounded px-3 py-2 mb-3">
    <div class="text-muted small mb-1">
        @if($kept !== null)
            <i class="fa fa-lock fa-fw" aria-hidden="true"></i>
            @if($kept->isLive())
                Tier reading saved when the order was created, {{ $kept->taken_at?->format('Y-m-d H:i') }}.
            @else
                Tier reading rebuilt for {{ $kept->as_of_date?->format('Y-m-d') }} from the stored history (the order
                predates saved readings).
            @endif
            Locked: it does not change with time.
        @elseif($isNewDecision)
            Tier reading for {{ $symbol }}, live. The symbol or action changed, so this is a new decision: the reading
            is saved with the order when you click Save, then locked.
        @else
            Tier reading for {{ $symbol }}, live. Saved with the order when you click Create, then locked.
        @endif
    </div>
    @include('myfinance2::watchlistsymbols.tables.partials.tier-reading', [
        'reading' => $reading, 'tierExtra' => $extra, 'idSuffix' => '-order',
    ])
    @if($order !== null)
        <div class="text-muted small mt-2">
            Changing the price, quantity or description keeps the saved reading (the warnings are checked again).
            Changing the symbol or action takes a new reading, with new warnings and a new confirmation.
        </div>
    @endif
</div>

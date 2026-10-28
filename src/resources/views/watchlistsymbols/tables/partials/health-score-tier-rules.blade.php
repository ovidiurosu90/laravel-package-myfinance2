{{-- How a tier is decided, in the health card's help text. Follows the configured basis
     (myfinance2.watchlistsymbols.tier_basis): the 1Y market return for every symbol, or the legacy own-return rule.
     --}}
@php
    use ovidiuro\myfinance2\App\Services\MoneyFormat;

    $isMarketBasis = config('myfinance2.watchlistsymbols.tier_basis', 'market') === 'market';
    $consistency   = (float) config('myfinance2.watchlistsymbols.tier_history.consistency_pct', 50);
    $minDays       = (int) config('myfinance2.watchlistsymbols.tier_history.min_history_days', 60);
    $persistDays   = (int) config('myfinance2.watchlistsymbols.tier_history.deteriorate_persist_days', 20);
    $shareText     = $consistency === 50.0
        ? 'at least half of the last 12 months\' trading days (the median daily reading)'
        : 'at least ' . MoneyFormat::get_formatted_number_plain($consistency, 0)
            . '% of the trading days of the last 12 months';
@endphp
@if($isMarketBasis)
    <span class="text-body">Market Settled</span>, the 1Y market return the symbol held or beat on
    {{ $shareText }}, on the lines 0 / 5 / 10 / 15 %; the same yardstick for every symbol. Today's tier
    (Market Snapshot on the row's Tier line) is used with under {{ $minDays }} days of history, or after
    {{ $persistDays }} trading days two tiers below (for Bronze, at or under &minus;5%).
    Without a usable market figure (missing, or below
    &minus;75%, usually a missed split), your own return; above +200% the figure is used and flagged.
    A manual override (&#9733;) always wins.
@else
    The settled tier: the level the symbol held or beat on {{ $shareText }}. Today's reading follows this
    priority order:
    <ol class="mb-1 mt-1 ps-3">
        <li>
            <span class="fw-semibold text-body">Held &ge; 1 year &rarr; annualized return (CAGR)</span>
            across all holding windows.
        </li>
        <li>
            <span class="fw-semibold text-body">Held 90 days to under 1 year &rarr; raw return</span>,
            not annualized, so a fast short-term gain is never projected into a higher tier.
        </li>
        <li>
            <span class="fw-semibold text-body">Held under 90 days &rarr; market 1Y</span>.
            Returns below &minus;75% are treated as artifacts (usually a missed split); raw return
            is used instead
            <i class="fa fa-exclamation-circle fa-xs text-warning" aria-hidden="true"></i>.
        </li>
    </ol>
    Watchlist-only and exited positions lead with market 1Y, falling back to their realized return if
    unavailable. A manual override always takes precedence.
    A <i class="fa fa-exclamation-circle fa-xs text-warning" aria-hidden="true"></i> icon marks positions
    held under 90 days where market 1Y was unavailable or rejected; the tier is provisional until the
    position matures.
@endif

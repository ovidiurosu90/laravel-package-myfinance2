{{--
    The Tier line and the Tier History line of a symbol, from a tier reading (TierReadingBuilder): the
    /watchlist-symbols row renders it live, the order and trade pages render the reading saved with the order or
    trade (TierSnapshot), so both look the same.
    $reading   the reading array
    $tierEdit  optional: the tier override button's data (current, computed, has_override, note); the watchlist only
    $tierExtra optional HTML appended to the Tier line (the order pages: "after this order", "now" chip)
    $noChart   optional: no Chart button (a page without the tier history modal)
--}}
@php
    use ovidiuro\myfinance2\App\Services\TierCalculationService;
    use ovidiuro\myfinance2\App\Services\MoneyFormat;

    $symbol       = $reading['symbol'];
    $tier         = $reading['effective_tier'] ?? null;
    $hasOverride  = !empty($reading['has_override']);
    $cells        = $reading['cells'] ?? [];
    $history      = $reading['history'] ?? null;
    $chips        = $reading['chips'] ?? [];
    $portfolio    = $reading['portfolio'] ?? null;
    $approachesId = 'tier-approaches-' . preg_replace('/[^A-Za-z0-9]+/', '-', $symbol) . ($idSuffix ?? '');
@endphp
{{-- Line 1: Tier (with the edit button for an owned symbol), under each of the 7 approaches; the one in use is
     the tier itself (full name, outlined), a manual override shows its own badge first. Then the share of the
     portfolio. --}}
<div class="d-flex flex-wrap align-items-center" style="column-gap:0.6rem;row-gap:0.15rem;">
    <span class="text-muted text-nowrap">@include('myfinance2::watchlistsymbols.tables.partials.superstar-star', [
        'superstar' => $reading['superstar'] ?? null,
    ])<span data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
        title="The tier under each of the 7 ways of measuring return compared in the Portfolio Health card.
            The outlined one is in use: it is the symbol's tier. * marks a stand-in."
        >Tier</span>@if(!empty($tierEdit))<span class="ms-1" data-bs-toggle="tooltip"
            title="Edit tier for {{ $symbol }}" style="line-height:1;vertical-align:middle;">
            <button type="button" class="btn btn-link btn-sm p-0"
                data-symbol="{{ $symbol }}"
                data-current-tier="{{ $tierEdit['current'] }}"
                data-computed-tier="{{ $tierEdit['computed'] }}"
                data-has-override="{{ $tierEdit['has_override'] ? 'true' : 'false' }}"
                data-override-note="{{ $tierEdit['note'] ?? '' }}"
                data-bs-toggle="modal"
                data-bs-target="#tier-override-modal">
                <i class="fa fa-pencil fa-xs text-muted" aria-hidden="true"></i>
            </button></span>@endif:</span>
    @if($tier === null)
        <span class="badge bg-light text-dark border"
            data-bs-toggle="tooltip" title="{{ $reading['explanation'] ?? '' }}">Unrated</span>
    @elseif($hasOverride || empty($cells))
        <span class="badge {{ TierCalculationService::tierBadgeClass($tier) }}"
            @if($tier === TierCalculationService::BRONZE) style="background-color:#e67e22!important;"@endif
            data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
            title="{{ $reading['tier_tooltip'] ?? '' }}">
            {{ TierCalculationService::tierLabel($tier) }}{{ $hasOverride ? ' ★' : '' }}
        </span>
    @endif
    @include('myfinance2::watchlistsymbols.tables.partials.tier-approaches', [
        'cells' => $cells, 'detailsId' => $approachesId,
    ])
    @foreach($reading['flags'] ?? [] as $flag)
    <span data-bs-toggle="tooltip" title="{{ $flag['title'] }}" style="line-height:1;vertical-align:middle;">
        <i class="fa {{ $flag['icon'] }} fa-xs {{ $flag['color'] }}" aria-hidden="true"></i>
    </span>
    @endforeach
    @if($portfolio !== null)
        <span class="text-muted">·</span>
        <span class="text-nowrap" data-bs-toggle="tooltip"
            title="€ {{ MoneyFormat::get_formatted_price_plain($portfolio['mvalue_eur']) }} current market value"
            @if(!empty($portfolio['color'])) style="color:{{ $portfolio['color'] }}" @endif
            >{{ $portfolio['pct'] }}% of portfolio</span>
    @endif
    {!! $tierExtra ?? '' !!}
</div>

@include('myfinance2::watchlistsymbols.tables.partials.tier-approaches-table', [
    'cells' => $cells, 'detailsId' => $approachesId,
])

{{-- Line 2: Tier history (the per-day ribbon, why there is no median when there is none, the split-year note and the
     chart), then the range-bound chip (TRIM / HOLD / ADD ...) when the symbol is range-bound, or the weak-tier-in-
     profit chip (TAKE PROFIT), or the tier signal chip (rare high, rare low, deteriorating; it shows nothing when it
     defers to one of the other two), and the near-peak chip on a held position near a window's peak (never with the
     first two) --}}
@if($history !== null || !empty($chips['range_bound']) || !empty($chips['take_profit'])
    || !empty($chips['tier_signal']) || !empty($chips['near_peak']))
<div class="d-flex flex-wrap gap-1 align-items-center mt-1">
    @if($history !== null)
        <span class="text-muted">Tier History 12M:</span>
        @if(empty($history['settled_shown']))
            <span class="text-muted fst-italic" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
                title="{{ $history['note_title'] }}">{{ $history['note'] }}</span>
        @endif
        @include('myfinance2::watchlistsymbols.tables.partials.tier-history-ribbon')
        @if(!empty($history['split']))
            <span class="text-muted text-nowrap" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
                title="{{ $history['split']['title'] }}"><i
                class="fa fa-exclamation-triangle fa-xs text-warning" aria-hidden="true"></i>
                {{ $history['split']['label'] }}</span>
        @endif
        @if(empty($noChart))
            @include('myfinance2::watchlistsymbols.tables.partials.tier-history-chart-button')
        @endif
    @endif
    @include('myfinance2::watchlistsymbols.tables.partials.range-bound-chip', [
        'range' => $chips['range_bound'] ?? null,
    ])
    @include('myfinance2::watchlistsymbols.tables.partials.take-profit-chip', [
        'takeProfit' => $chips['take_profit'] ?? null,
    ])
    @include('myfinance2::watchlistsymbols.tables.partials.tier-signal-chip', [
        'tierSignal' => $chips['tier_signal'] ?? null,
    ])
    @include('myfinance2::watchlistsymbols.tables.partials.near-peak-chip', [
        'nearPeak' => $chips['near_peak'] ?? null,
    ])
</div>
@endif

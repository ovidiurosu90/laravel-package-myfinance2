{{--
    A saved tier reading (TierSnapshot) in a list cell (the order list, the trade list): the verdict icon (tooltip:
    the saved warnings), then the watchlist table's compact Tier cell look (tier-badge: the tier in use, the compact
    chips, the superstar star), and the 7 approaches' initials beneath, in the watchlist row's order. Hovering any
    badge shows the tooltip saved with the reading.
    $snapshot  the TierSnapshot, or null (none saved)
    $verdictTip optional: the verdict tooltip (HTML); TierVerdictPresenter::tooltip($snapshot) when absent
    $tierExtra optional HTML after the badges (the order list's "now" chip)
    $tierNote  optional HTML on its own line below (the trade list's "from order #N")
--}}
@php
    use ovidiuro\myfinance2\App\Models\TierSnapshot;
    use ovidiuro\myfinance2\App\Services\TierCalculationService;
    use ovidiuro\myfinance2\App\Services\TierVerdictPresenter;

    $reading = $snapshot?->reading;
    $tier    = $reading['effective_tier'] ?? null;
    $chips   = $reading['chips'] ?? [];
@endphp
@if($snapshot === null)
    <span class="text-muted small" data-bs-toggle="tooltip"
        title="No tier reading saved: created before readings were saved.">-</span>
@elseif($snapshot->source === TierSnapshot::SOURCE_NONE)
    <span class="text-muted small fst-italic" data-bs-toggle="tooltip" data-bs-html="true"
        data-bs-custom-class="tooltip-wide" title="{{ TierVerdictPresenter::tooltip($snapshot) }}"
        >no tier history</span>
@else
    @php
        [$icon, $color] = TierVerdictPresenter::icon($snapshot->verdict);
    @endphp
    {{-- Kept about as wide as the 7 initials: the chips wrap under the tier instead of widening the column. --}}
    <div class="d-flex flex-wrap align-items-center" style="max-width:10rem;gap:0.2rem 0.25rem;">
        <span class="text-nowrap"><span data-bs-toggle="tooltip" data-bs-html="true"
            data-bs-custom-class="tooltip-wide" title="{{ $verdictTip ?? TierVerdictPresenter::tooltip($snapshot) }}"><i
            class="fa {{ $icon }} {{ $color }} fa-fw" aria-hidden="true"></i></span>
        @if($tier === null)
            <span class="badge bg-light text-dark border" data-bs-toggle="tooltip"
                title="{{ $reading['explanation'] ?? '' }}">Unrated</span>
        @else
            <span class="badge {{ TierCalculationService::tierBadgeClass($tier) }}"
                @if($tier === TierCalculationService::BRONZE) style="background-color:#e67e22!important;"@endif
                data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
                title="{{ $reading['tier_tooltip'] ?? '' }}">
                {{ TierCalculationService::tierLabel($tier) }}{{ !empty($reading['has_override']) ? ' ★' : '' }}
            </span>
        @endif
        @include('myfinance2::watchlistsymbols.tables.partials.superstar-star', [
            'superstar' => $reading['superstar'] ?? null,
        ])</span>
        @include('myfinance2::watchlistsymbols.tables.partials.range-bound-chip', [
            'range' => $chips['range_bound'] ?? null, 'compact' => true,
        ])
        @include('myfinance2::watchlistsymbols.tables.partials.take-profit-chip', [
            'takeProfit' => $chips['take_profit'] ?? null, 'compact' => true,
        ])
        @include('myfinance2::watchlistsymbols.tables.partials.tier-signal-chip', [
            'tierSignal' => $chips['tier_signal'] ?? null, 'compact' => true,
        ])
        @include('myfinance2::watchlistsymbols.tables.partials.near-peak-chip', [
            'nearPeak' => $chips['near_peak'] ?? null, 'compact' => true,
        ])
    </div>
    @if(!empty($reading['cells']))
        <div class="text-nowrap mt-1" style="font-size:0.7em;line-height:1;">
            @foreach($reading['cells'] as $cell)
                @php $tier = $cell['tier'] ?? null; @endphp
                <span data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" title="{{ $cell['tip'] }}"
                    @if($cell['in_use']) class="border border-primary rounded" @endif
                    >@include('myfinance2::watchlistsymbols.tables.partials.tier-approach-badge', [
                        'full' => false,
                    ])</span>
            @endforeach
            @if($snapshot->source === TierSnapshot::SOURCE_REBUILT)
                <i class="fa fa-rotate-left text-muted ms-1" aria-hidden="true" data-bs-toggle="tooltip"
                    title="Rebuilt once from the stored tier history, then locked"></i>
            @endif
        </div>
    @endif
    @if(!empty($tierExtra))
        <div class="mt-1">{!! $tierExtra !!}</div>
    @endif
@endif
@if(!empty($tierNote))
    <div class="text-muted small">{!! $tierNote !!}</div>
@endif

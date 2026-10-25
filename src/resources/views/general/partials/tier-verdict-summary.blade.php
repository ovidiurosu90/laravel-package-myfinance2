{{-- The "last 12 months vs the tiers" counts of the order and trade lists (TierVerdictSummary), compact: per action
     the count and the verdict icons of the Tier column, the full breakdown (confirmed, rebuilt) in each action's
     tooltip. Rendered hidden; scripts/tier-verdict-summary moves it into the table's toolbar, between "entries per
     page" and the search, so it takes no line of its own. $tierSummary: TierVerdictSummary::build(); $tierNoun:
     "orders" or "trades". --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@use('ovidiuro\myfinance2\App\Services\OrderTierWarnings')
@use('ovidiuro\myfinance2\App\Services\TierVerdictPresenter')
@use('ovidiuro\myfinance2\App\Services\TierVerdictSummary')
@if(!empty($tierSummary))
@php
    $tierLines = TierVerdictSummary::lines($tierSummary, $tierNoun);
    $tierIcons = [
        'against' => TierVerdictPresenter::icon(OrderTierWarnings::VERDICT_AGAINST),
        'soft'    => TierVerdictPresenter::icon(OrderTierWarnings::VERDICT_SOFT),
        'ok'      => TierVerdictPresenter::icon(OrderTierWarnings::VERDICT_OK),
    ];
@endphp
<div id="tier-verdict-summary" class="d-none align-items-center small text-nowrap" style="column-gap:0.9rem;">
    <span class="text-muted" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
        title="The {{ $tierNoun }} of the last 12 months by the verdict of their tier reading: against the tiers
            (serious warnings; confirmed when saved through the warning), soft warnings only, or no warning (not the
            same as the tiers endorsing them). Rebuilt readings come from the stored tier history (no manual override,
            portfolio share or superstar), so they are counted apart. On the trade list, the number in brackets after
            each count is the median of those trades against VUSA.AS, in points (the Vs Index column): positive when
            the decisions were right. Hover an action for its breakdown."
        >12M vs tiers <i class="fas fa-info-circle fa-xs" aria-hidden="true"></i></span>
    @foreach($tierSummary as $action => $row)
        <span data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" title="{{ $tierLines[$action] }}">
            <strong>{{ $action }}</strong> {{ $row['total'] }}
            @foreach($tierIcons as $key => [$icon, $color])
                @if($row[$key] > 0)
                    @php
                        // The median against the index in whole points, signed: "(-6)", "(+3)".
                        $median     = $row['median'][$key] ?? null;
                        $medianText = $median === null ? null : ($median >= 0 ? '+' : '−')
                            . MoneyFormat::get_formatted_number_plain(abs($median), 0);
                    @endphp
                    <span class="ms-1"><i class="fa {{ $icon }} {{ $color }}"
                        aria-hidden="true"></i>{{ $row[$key] }}@if($medianText !== null)<span
                        @class(['ms-1', 'text-success' => $median >= 0, 'text-danger' => $median < 0])
                        >({{ $medianText }})</span>@endif</span>
                @endif
            @endforeach
        </span>
    @endforeach
</div>
@endif

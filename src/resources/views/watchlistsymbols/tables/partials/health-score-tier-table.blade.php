@php use ovidiuro\myfinance2\App\Services\MoneyFormat; @endphp
@php use ovidiuro\myfinance2\App\Services\TierCalculationService; @endphp
@php
    $tierRowStyles = [
        TierCalculationService::PLATINUM => 'background-color:rgba(13,202,240,0.18)',
        TierCalculationService::GOLD     => 'background-color:rgba(255,215,0,0.35)',
        TierCalculationService::SILVER   => 'background-color:rgba(108,117,125,0.18)',
        TierCalculationService::BRONZE   => 'background-color:rgba(205,127,50,0.38)',
        TierCalculationService::RUST     => 'background-color:rgba(220,53,69,0.25)',
    ];
@endphp
@if(!empty($symbolRows))
<table id="{{ $tableId }}" class="health-tier-dt w-100" style="font-size:0.75rem;white-space:nowrap;line-height:1.4;font-variant-numeric:tabular-nums lining-nums">
    <thead>
        <tr>
            <th>Symbol</th>
            <th>Cost €</th>
            <th>MValue €</th>
            <th>Gain/y</th>
            <th>Holding period</th>
        </tr>
    </thead>
    <tbody>
    @foreach($symbolRows as $row)
    @php
        $tier     = $row['tier'];
        $rowStyle = $tierRowStyles[$tier] ?? '';
    @endphp
    <tr style="{{ $rowStyle }}">
        <td class="text-end align-top border-bottom">
            <div class="fw-semibold">
                @php
                    $rowTier     = $row['tier'] ?? null;
                    $rowOverride = $row['has_override'] ?? false;
                    // For an override the explanation carries the override reason and the
                    // original assessment; otherwise just name the tier.
                    $rowTierTip  = $rowOverride && !empty($row['explanation'])
                        ? $row['explanation']
                        : TierCalculationService::tierLabel($rowTier);
                @endphp
                @if($rowTier !== null)
                <span class="badge {{ TierCalculationService::tierBadgeClass($rowTier) }} me-1"
                    @if($rowTier === TierCalculationService::BRONZE) style="background-color:#e67e22!important;"@endif
                    data-bs-toggle="tooltip"
                    title="{{ $rowTierTip }}">{{ TierCalculationService::tierInitial($rowTier) }}{{ $rowOverride ? ' ★' : '' }}</span>
                @else
                <span class="badge bg-light text-dark border me-1"
                    data-bs-toggle="tooltip"
                    title="{{ $row['explanation'] ?? 'Unrated: no usable return data' }}">U</span>
                @endif
                {{ $row['symbol'] }}
            </div>
            @if(!empty($row['show_current']))
            <div class="fst-italic fw-normal text-muted">overall</div>
            @endif
        </td>
        <td data-order="{{ $row['cost_eur'] }}" class="text-end text-nowrap align-top border-bottom">
            <div>
                <span style="color:{{ $health_score['cost_color'] }}">{!! MoneyFormat::get_formatted_price_display('&euro;', $row['cost_eur']) !!}</span>
                <span class="text-muted">({{ $row['cost_pct'] }}%)</span>
            </div>
            @if(!empty($row['show_current']) && ($row['overall_cost_eur'] ?? null) !== null)
            <div>
                <span style="color:{{ $health_score['cost_color'] }}">{!! MoneyFormat::get_formatted_price_display('&euro;', $row['overall_cost_eur']) !!}</span>
            </div>
            @endif
        </td>
        <td data-order="{{ $row['mvalue_eur'] }}" class="text-end text-nowrap align-top border-bottom">
            <div class="fw-bold" data-bs-toggle="tooltip"
                 title="Current market value; this is what counts toward the portfolio allocation">
                <span style="color:{{ $health_score['mvalue_color'] }}">{!! MoneyFormat::get_formatted_price_display('&euro;', $row['mvalue_eur']) !!}</span>
                <span class="text-muted">({{ $row['mvalue_pct'] }}%)</span>
            </div>
            @if(!empty($row['show_current']) && ($row['overall_mvalue_eur'] ?? null) !== null)
            <div>
                <span style="color:{{ $health_score['mvalue_color'] }}">{!! MoneyFormat::get_formatted_price_display('&euro;', $row['overall_mvalue_eur']) !!}</span>
            </div>
            @endif
        </td>
        @include('myfinance2::watchlistsymbols.tables.partials.health-score-gain-cell', ['row' => $row])
        @include('myfinance2::watchlistsymbols.tables.partials.health-score-position-cell', ['row' => $row])
    </tr>
    @endforeach
    </tbody>
</table>
@endif

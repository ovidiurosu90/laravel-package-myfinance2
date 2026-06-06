@php
    use ovidiuro\myfinance2\App\Services\MoneyFormat;
    use ovidiuro\myfinance2\App\Services\TierDecision;
    use ovidiuro\myfinance2\App\Services\SymbolPerformanceService;
    use ovidiuro\myfinance2\App\Services\ReturnsTooltips;

    $basis       = $row['basis'] ?? TierDecision::BASIS_NONE;
    $confidence  = $row['confidence'] ?? TierDecision::CONFIDENCE_HIGH;
    $explanation = $row['explanation'] ?? '';

    $currentAnnPct = $row['annualized_pct'] ?? null;   // current open window, annualized
    $annEur        = $row['annualized_gain_eur'] ?? null;
    $rawGainEur    = $row['raw_gain_eur'] ?? null;
    $rawGainPct    = $row['raw_gain_pct'] ?? null;
    $overallAnnEur = $row['overall_ann_eur'] ?? null;
    $overallAnnPct = $row['overall_ann_pct'] ?? null;
    $overallRawEur = $row['overall_raw_eur'] ?? null;
    $overallRawPct = $row['overall_raw_pct'] ?? null;
    $market1y      = $row['market_1y_pct'] ?? null;
    $xirrPct       = $row['xirr_pct'] ?? null;            // money-weighted (your euro timing)
    $totalDays     = (int) ($row['total_days'] ?? 0);
    $xirrMinDays   = SymbolPerformanceService::MIN_ANNUALIZED_DAYS;
    $xirrShort     = $xirrPct !== null && $totalDays > 0 && $totalDays < SymbolPerformanceService::MIN_CAGR_DAYS;
    $xirrNaTip     = ReturnsTooltips::xirrNa($totalDays, $xirrMinDays);
    $xirrTip       = ReturnsTooltips::xirr($xirrShort);
    $alphaPct      = $row['alpha_vs_vusa_pct'] ?? null;   // your CAGR minus VUSA CAGR, same window
    $alphaShort    = !empty($row['alpha_is_short_period']); // held < 1y: raw difference, provisional
    $alphaTip      = ReturnsTooltips::alpha(
        $alphaShort,
        $row['vusa_same_window_pct'] ?? null,
        $row['vusa_same_window_raw_pct'] ?? null
    );
    $showOverall   = !empty($row['show_overall']);
    $showCurrent   = !empty($row['show_current']);

    // The overall line carries the figure that decides any return-based tier; the
    // market line carries it for momentum-based tiers.
    $isStale     = !empty($row['is_stale']);
    $boldOverall = in_array($basis, [TierDecision::BASIS_ANNUALIZED_RETURN, TierDecision::BASIS_RAW_RETURN]);
    $boldMarket  = $basis === TierDecision::BASIS_MARKET_MOMENTUM;
    $isLow       = $confidence === TierDecision::CONFIDENCE_LOW;

    $orderValue = $row['basis_value'] ?? ($overallAnnPct ?? $rawGainPct ?? -999999);

    $warnIcon = '<span data-bs-toggle="tooltip" title="' . e($explanation)
        . '" style="line-height:1;vertical-align:middle;">'
        . '<i class="fa fa-exclamation-circle fa-xs text-warning" aria-hidden="true"></i></span>';
    // Stale headline (re-entered position) uses a clock icon, matching the tier line and badge.
    $staleIcon = '<span data-bs-toggle="tooltip" title="' . e($explanation)
        . '" style="line-height:1;vertical-align:middle;">'
        . '<i class="fa fa-clock-o fa-xs text-warning" aria-hidden="true"></i></span>';
@endphp
<td data-order="{{ $orderValue }}" class="text-end align-top border-bottom">
    @if($showCurrent)
    <div class="text-nowrap text-muted">
        @if($currentAnnPct !== null)
        <span data-bs-toggle="tooltip" title="Current open position, annualized return (CAGR): the steady yearly rate that compounds to the position's total return. Comparable to an index.">
            {!! MoneyFormat::get_formatted_gain('&euro;', $annEur) !!}
            <span>(</span>{!! MoneyFormat::get_formatted_gain('%', $currentAnnPct) !!}<span>)</span>
        </span>
        @else
        <span data-bs-toggle="tooltip" title="Current open position, raw gain (not yet annualized)">
            {!! MoneyFormat::get_formatted_gain('&euro;', $rawGainEur) !!}
            <span>(</span>{!! MoneyFormat::get_formatted_gain('%', $rawGainPct) !!}<span>)</span>
        </span>
        @endif
    </div>
    @endif

    @if($showOverall)
    @php
        $oEur = $overallAnnPct !== null ? $overallAnnEur : $overallRawEur;
        $oPct = $overallAnnPct !== null ? $overallAnnPct : $overallRawPct;
        $oLbl = $overallAnnPct !== null ? 'Overall annualized return (CAGR)' : 'Overall raw gain';
    @endphp
    <div class="text-nowrap{{ $boldOverall ? ' fw-bold' : ' text-muted' }}">
        <span data-bs-toggle="tooltip"
              title="{{ $oLbl }} across all holding windows{{ $boldOverall ? ' (decides tier)' : '' }}">
            {!! MoneyFormat::get_formatted_gain('&euro;', $oEur) !!}
            <span>(</span>{!! MoneyFormat::get_formatted_gain('%', $oPct) !!}<span>)</span>
        </span>
        @if($boldOverall && $isLow){!! $warnIcon !!}@endif
        @if($boldOverall && $isStale){!! $staleIcon !!}@endif
    </div>
    @endif

    <div class="text-nowrap{{ $boldMarket ? ' fw-bold' : '' }}">
        <span data-bs-toggle="tooltip"
              title="Symbol market return over the trailing 12 months{{ $boldMarket ? ' (decides tier)' : '' }}">
            <span class="text-muted">market</span>
            @if($market1y !== null){!! MoneyFormat::get_formatted_gain('%', $market1y) !!}@else<span class="text-muted">n/a</span>@endif
        </span>
        @if($boldMarket && $isLow){!! $warnIcon !!}@endif
    </div>

    @if($showOverall)
    {{-- Money-weighted return: the "how my actual euros did" view, distinct from the CAGR above.
         Shown as n/a (with the reason) under 30 days, where annualizing would be meaningless. --}}
    <div class="text-nowrap text-muted">
        @if($xirrPct !== null)
        <span data-bs-toggle="tooltip" title="{{ $xirrTip }}">
            XIRR {!! MoneyFormat::get_formatted_gain('%', $xirrPct) !!}@if($xirrShort)<sup>*</sup>@endif
        </span>
        @else
        XIRR <span data-bs-toggle="tooltip" title="{{ $xirrNaTip }}">n/a</span>
        @endif
    </div>
    @endif

    @if($alphaPct !== null)
    {{-- Alpha vs VUSA.AS over the same holding window; raw difference (flagged *) when held < 1y. --}}
    <div class="text-nowrap text-muted">
        <span data-bs-toggle="tooltip" title="{{ $alphaTip }}">
            vs VUSA.AS {!! MoneyFormat::get_formatted_gain('%', $alphaPct) !!}@if($alphaShort)<sup>*</sup>@endif
        </span>
    </div>
    @endif
</td>

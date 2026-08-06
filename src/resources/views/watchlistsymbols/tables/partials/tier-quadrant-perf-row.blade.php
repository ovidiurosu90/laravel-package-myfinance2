@php
use ovidiuro\myfinance2\App\Services\TierCalculationService;
use ovidiuro\myfinance2\App\Services\TierDecision;
use ovidiuro\myfinance2\App\Services\QuadrantClassifier;
use ovidiuro\myfinance2\App\Services\MoneyFormat;
use ovidiuro\myfinance2\App\Services\ReturnsTooltips;

$cat          = $quoteData['categorization'] ?? null;
$tier         = $cat['effective_tier'] ?? null;
$computedTier = $cat['computed_tier'] ?? null;
$hasOverride  = $cat['has_override'] ?? false;
$confidence   = $cat['confidence'] ?? TierDecision::CONFIDENCE_HIGH;
$explanation  = $cat['explanation'] ?? '';
$basis        = $cat['basis'] ?? null;
$basisValue   = $cat['basis_value'] ?? null;
$action       = $cat['action'] ?? null;
// The 1Y market proxy was rejected by the trusted band and the tier fell back: an irregularity
// worth flagging on its own, with a specific note explaining the clamp.
$marketArtifact     = !empty($cat['market_artifact']);
$marketArtifactNote = $cat['market_artifact_note'] ?? '';
// Generic low-confidence warning. Suppressed when the artifact icon already explains the fallback,
// so the same fallback is not flagged twice.
$showWarn     = $cat !== null && !$hasOverride && !$marketArtifact
                && $confidence === TierDecision::CONFIDENCE_LOW;
$isStale      = !empty($cat['is_stale']);
$isOwned      = !empty($quoteData['open_positions']);
$isBenchmark  = !empty($cat['is_benchmark']);
$isAnn        = $basis === TierDecision::BASIS_ANNUALIZED_RETURN;
$basisLabel   = match($basis) {
    TierDecision::BASIS_ANNUALIZED_RETURN,
    TierDecision::BASIS_RAW_RETURN        => 'overall return',
    TierDecision::BASIS_MARKET_MOMENTUM   => '1Y market return',
    default                               => null,
};

// EUR gain shown on the tier line (matches what decided the tier), built in the BE.
$gainEur = $quoteData['table_meta']['basis_gain_eur'] ?? null;

// Per-period "P&L if sold at this window's peak" (EUR + %), built in the BE (PeakExitPnlBuilder).
// Owned symbols only; null per period when there is no held position or no window peak.
$peakPnlMap = $quoteData['table_meta']['period_peak_pnl'] ?? [];

// Portfolio mvalue from health score lookup (built in items-table.blade.php)
$hsRow      = ($healthSymbolIndex ?? [])[$symbol] ?? null;
$mvalueEur  = $hsRow['mvalue_eur'] ?? null;
$mvaluePct  = $hsRow['mvalue_pct'] ?? null;
$mvalueColor = $health_score['mvalue_color'] ?? null;

$quadrantColors = [
    QuadrantClassifier::STEADY_GROWERS   => 'bg-success',
    QuadrantClassifier::VOLATILE_WINNERS => 'bg-warning text-dark',
    QuadrantClassifier::DEAD_WEIGHT      => 'bg-secondary',
    QuadrantClassifier::DANGER_ZONE      => 'bg-danger',
];
$actionColors = [
    'ACCUMULATE' => 'bg-success',
    'HOLD'       => 'bg-info text-dark',
    'REDUCE'     => 'bg-warning text-dark',
    'EXIT'       => 'bg-danger',
];
@endphp
@if($cat !== null)
<div class="symbol-performance mt-1">

    {{-- Line 1: Tier --}}
    <div class="d-flex flex-wrap gap-1 align-items-center">
        <span class="text-muted">Tier:</span>
        @if($tier === null)
            <span class="badge bg-light text-dark border"
                data-bs-toggle="tooltip" title="{{ $explanation }}">Unrated</span>
        @else
            @php
                $badgeClass = TierCalculationService::tierBadgeClass($tier);
                $isOrange   = $tier === TierCalculationService::BRONZE;
                $tierLabel  = TierCalculationService::tierLabel($tier);
            @endphp
            <span class="badge {{ $badgeClass }}"
                @if($isOrange) style="background-color:#e67e22!important;"@endif
                @if($hasOverride) data-bs-toggle="tooltip" title="{{ $explanation }}"@endif>
                {{ $tierLabel }}{{ $hasOverride ? ' ★' : '' }}
            </span>
            @if($isOwned)
            <span data-bs-toggle="tooltip" title="Edit tier for {{ $symbol }}"
                  style="line-height:1;vertical-align:middle;">
                <button type="button" class="btn btn-link btn-sm p-0"
                    data-symbol="{{ $symbol }}"
                    data-current-tier="{{ $tier }}"
                    data-computed-tier="{{ $computedTier }}"
                    data-has-override="{{ $hasOverride ? 'true' : 'false' }}"
                    data-override-note="{{ $cat['override_note'] ?? '' }}"
                    data-bs-toggle="modal"
                    data-bs-target="#tier-override-modal">
                    <i class="fa fa-pencil fa-xs text-muted" aria-hidden="true"></i>
                </button>
            </span>
            @endif
            @if($basisValue !== null)
                <span class="text-muted">based on{{ $basisLabel !== null ? ' (' . $basisLabel . ')' : '' }}</span>
                <span data-bs-toggle="tooltip" title="{{ $explanation }}">
                    @if($gainEur !== null)
                        {!! MoneyFormat::get_formatted_gain('&euro;', $gainEur) !!}
                        <span class="text-muted">(</span>{!! MoneyFormat::get_formatted_gain('%', $basisValue) !!}@if($isAnn)<span class="text-muted">/y</span>@endif<span class="text-muted">)</span>
                    @else
                        {!! MoneyFormat::get_formatted_gain('%', $basisValue) !!}@if($isAnn)<span class="text-muted">/y</span>@endif
                    @endif
                </span>
            @endif
            @if($isStale)
            <span data-bs-toggle="tooltip" title="{{ $explanation }}"
                  style="line-height:1;vertical-align:middle;">
                <i class="fa fa-clock-o fa-xs text-warning" aria-hidden="true"></i>
            </span>
            @endif
            @if($marketArtifact)
            <span data-bs-toggle="tooltip" title="{{ $marketArtifactNote }}"
                  style="line-height:1;vertical-align:middle;">
                <i class="fa fa-exclamation-triangle fa-xs text-danger" aria-hidden="true"></i>
            </span>
            @endif
            @php
                // CAGR (the tier basis above) measures the asset while held and is what the index
                // is compared against. XIRR and the VUSA alpha are the two extra "per year" views.
                $xirrPct    = $cat['xirr_pct'] ?? null;
                $xirrShort  = !empty($cat['xirr_is_short_period']); // held < 1y: annualized rate is provisional
                $xirrTip    = ReturnsTooltips::xirr($xirrShort);
                $alphaPct   = $cat['alpha_vs_vusa_pct'] ?? null;
                $alphaShort = !empty($cat['alpha_is_short_period']);
                $alphaTip   = ReturnsTooltips::alpha(
                    $alphaShort,
                    $cat['vusa_same_window_pct'] ?? null,
                    $cat['vusa_same_window_raw_pct'] ?? null
                );
            @endphp
            @if($mvalueEur !== null)
                <span class="text-muted">·</span>
                <span data-bs-toggle="tooltip"
                    title="€ {{ MoneyFormat::get_formatted_price_plain($mvalueEur) }} current market value">
                    @if($mvalueColor)<span style="color:{{ $mvalueColor }}">current {{ $mvaluePct }}% of portfolio</span>@else<span>current {{ $mvaluePct }}% of portfolio</span>@endif
                </span>
            @endif
            @if($showWarn)
            <span data-bs-toggle="tooltip" title="{{ $explanation }}"
                  style="line-height:1;vertical-align:middle;">
                <i class="fa fa-exclamation-circle fa-xs text-warning" aria-hidden="true"></i>
            </span>
            @endif
        @endif
    </div>

    {{-- Line 2: Returns (money-weighted XIRR and alpha vs the benchmark), both per year. The
         benchmark does not compare against itself, so it just states what it is. --}}
    @if($tier !== null && (($xirrPct !== null && $isOwned) || $alphaPct !== null || $isBenchmark))
    <div class="d-flex flex-wrap gap-1 align-items-center mt-1">
        <span class="text-muted" data-bs-toggle="tooltip"
            title="{{ ReturnsTooltips::moneyWeightedLabel() }}">Money-weighted:</span>
        @if($xirrPct !== null && $isOwned)
            <span data-bs-toggle="tooltip" title="{{ $xirrTip }}">
                <span class="text-muted">XIRR</span> {!! MoneyFormat::get_formatted_gain('%', $xirrPct) !!}<span class="text-muted">/y</span>@if($xirrShort)<sup>*</sup>@endif
            </span>
        @endif
        @if($isBenchmark)
            @if($xirrPct !== null && $isOwned)<span class="text-muted">·</span>@endif
            <span class="text-muted fst-italic">is the benchmark</span>
        @elseif($alphaPct !== null)
            @if($xirrPct !== null && $isOwned)<span class="text-muted">·</span>@endif
            <span data-bs-toggle="tooltip" title="{{ $alphaTip }}">
                <span class="text-muted">benchmark</span> {!! MoneyFormat::get_formatted_gain('%', $alphaPct) !!}@if($alphaShort)<sup>*</sup>@else<span class="text-muted">/y</span>@endif
            </span>
        @endif
    </div>
    @endif

    {{-- Line 3: Quadrant table (per-period breakdown) --}}
    @php
        $ownedActionTooltips = [
            'ACCUMULATE' => 'Strong returns and low volatility; consider adding to your position.',
            'HOLD'       => 'Strong returns but high volatility; keep the position, avoid adding more.',
            'REDUCE'     => 'Low returns and low volatility; consider trimming your position.',
            'EXIT'       => 'Low returns and high volatility; consider closing the position.',
        ];
        $actionOutlineColors = [
            'ACCUMULATE' => 'text-success',
            'WATCH'      => 'text-info',
            'SKIP'       => 'text-secondary',
            'AVOID'      => 'text-danger',
        ];
        $actionTooltips = [
            'ACCUMULATE' => 'Strong returns and low volatility; a good candidate to enter a position.',
            'WATCH'      => 'Strong returns but high volatility; monitor before entering a position.',
            'SKIP'       => 'Low returns and low volatility; no compelling reason to enter.',
            'AVOID'      => 'Low returns and high volatility; not worth the risk.',
        ];
        $periods = $cat['periods'] ?? [];
        $tierCalc = new TierCalculationService();

        // Ready-to-display peak-price labels (native trade currency + EUR), built in the BE by
        // WatchlistTableMetaBuilder. The blade only echoes them; null per period when no usable peak.
        $peakLabels = $quoteData['table_meta']['peak_labels'] ?? [];

        // Current price in the same native+EUR format, shown above the peak in the "From peak"
        // tooltips so the proximity percentage is traceable (current vs peak). Null when no price.
        $currentPriceLabel = $quoteData['table_meta']['current_price_label'] ?? null;

        // Ready-to-display start/end price points behind each window's gain (EUR), built in the BE.
        // Used by the "Gain" tooltip so the percentage is traceable to two dated prices. Null per
        // period when the window has no usable price pair.
        $gainWindows = $quoteData['table_meta']['gain_windows'] ?? [];
    @endphp
    <div class="d-flex flex-wrap gap-1 align-items-start mt-1">
        <div class="text-muted" style="padding-top: 0.3rem;">
            <span data-bs-toggle="tooltip"
                title="Market return per period; your holdings only affect the 'P&L at peak' column.">Quadrant:</span>
        </div>
        @if(!empty($periods))
        <table class="table table-sm table-borderless mb-0" style="width:auto;">
            <thead>
                <tr class="text-muted">
                    <th></th>
                    <th><span data-bs-toggle="tooltip"
                        title="Tier this window's market return maps to (same thresholds as the headline tier, applied to the market gain in this row).">Tier</span></th>
                    <th>Quadrant</th>
                    <th class="text-end"><span data-bs-toggle="tooltip"
                        title="Market return over the window, measured from the price at the window start to the latest price (just those two points). 3M and 6M are raw returns; 1Y and 2Y are annualized (CAGR), so the 2Y figure is a per-year rate comparable to 1Y, not the full two-year total.">Gain</span></th>
                    <th class="text-end">Risk</th>
                    <th>Action</th>
                    <th class="text-end">From peak</th>
                    <th class="text-end"><span data-bs-toggle="tooltip"
                        title="P&L on your held shares if sold at this window's peak price, versus your purchase cost. The 3M figure is your realistic near-term ceiling.">P&amp;L at peak</span></th>
                </tr>
            </thead>
            <tbody>
            @foreach(['3m' => '3M', '6m' => '6M', '1y' => '1Y', '2y' => '2Y'] as $pKey => $pLabel)
            @php
                $pd       = $periods[$pKey] ?? null;
                $pdRisk   = $pd['risk'] ?? null;
                $pdRiskClass = $pdRisk === null ? ''
                    : ($pdRisk > 3.0 ? 'text-danger' : ($pdRisk > 2.0 ? 'text-warning' : 'text-success'));
                $pdAction = $pd['action'] ?? null;
                $pdEz     = $pd['exit_zone'] ?? null;
                $pdPnl    = $peakPnlMap[$pKey] ?? null;
                // Human-readable peak date (e.g. "02 Jun 2026"), matching the position-window
                // tooltips. peak_price_date is a Y-m-d string (the price-series key).
                $pdPeakDate = !empty($pdEz['peak_price_date'])
                    ? \Carbon\Carbon::parse($pdEz['peak_price_date'])->format('d M Y')
                    : 'n/a';
                $pdTier   = ($pd && ($pd['gain'] ?? null) !== null)
                    ? $tierCalc->getTier($pd['gain']) : null;
                $pdTierStyle = $pdTier === TierCalculationService::BRONZE
                    ? 'background-color:#e67e22!important;' : '';

                // "Gain" tooltip: the two dated EUR prices the percentage is measured between, so
                // the figure (and the tier it drives) is traceable. The 1Y/2Y badge shows an
                // annualized rate, so the tooltip also states the plain start->end move.
                $gw      = $gainWindows[$pKey] ?? null;
                $gainTip = null;
                if ($gw !== null) {
                    $rawPct  = $gw['raw_pct'];
                    $rawSign = $rawPct === null ? '' : (($rawPct >= 0.0 ? '+ ' : '- ')
                        . MoneyFormat::get_formatted_number_plain(abs($rawPct), 2) . ' %');
                    $gainTip = 'The percentage is the EUR move from the window-start price to the '
                        . 'latest price (native price shown for reference).'
                        . '<br>Start: ' . $gw['start_label'] . ' on ' . $gw['start_date']
                        . '<br>Latest: ' . $gw['end_label'] . ' on ' . $gw['end_date'];
                    if ($rawPct !== null) {
                        $gainTip .= $gw['is_annualized']
                            ? '<br>Plain move over the window: ' . $rawSign
                                . ', shown here annualized to the per-year rate in the badge.'
                            : '<br>Gain over the window: ' . $rawSign . '.';
                    }
                }
            @endphp
            <tr>
                <td class="text-muted">{{ $pLabel }}</td>
                <td>
                    @if($pdTier !== null)
                        <span class="badge opacity-50 {{ TierCalculationService::tierBadgeClass($pdTier) }}"
                            style="{{ $pdTierStyle }}" data-bs-toggle="tooltip"
                            title="{{ TierCalculationService::tierLabel($pdTier) }}"
                            >{{ TierCalculationService::tierInitial($pdTier) }}</span>
                    @else
                        <span class="text-muted small">-</span>
                    @endif
                </td>
                <td>
                    @if($pd && $pd['quadrant'])
                        <span class="badge opacity-50 {{ $quadrantColors[$pd['quadrant']] ?? 'bg-secondary' }}">
                            {{ QuadrantClassifier::label($pd['quadrant']) }}
                        </span>
                    @else
                        <span class="text-muted small">-</span>
                    @endif
                </td>
                <td class="text-end text-nowrap">
                    @if($pd && $pd['gain'] !== null)
                        @if($gainTip !== null)
                            <span data-bs-toggle="tooltip" data-bs-html="true"
                                  data-bs-custom-class="tooltip-wide" title="{!! $gainTip !!}">
                                {!! MoneyFormat::get_formatted_gain('%', $pd['gain']) !!}
                            </span>
                        @else
                            {!! MoneyFormat::get_formatted_gain('%', $pd['gain']) !!}
                        @endif
                    @else
                        <span class="text-muted small">-</span>
                    @endif
                </td>
                <td class="text-end">
                    @if($pdRisk !== null)
                        <span class="{{ $pdRiskClass }}">
                            {{ MoneyFormat::get_formatted_number_plain($pdRisk, 2) }}x
                        </span>
                    @else
                        <span class="text-muted small">-</span>
                    @endif
                </td>
                <td>
                    @if($pdAction !== null)
                        @if($isOwned)
                            <span class="badge opacity-50 {{ $actionColors[$pdAction] ?? 'bg-secondary' }}"
                                  data-bs-toggle="tooltip"
                                  title="{{ $ownedActionTooltips[$pdAction] ?? '' }}">
                                {{ ucfirst(strtolower($pdAction)) }}
                            </span>
                        @else
                            <span class="badge bg-transparent opacity-50 {{ $actionOutlineColors[$pdAction] ?? 'text-secondary' }}"
                                  style="border: 1px dotted currentColor;"
                                  data-bs-toggle="tooltip"
                                  title="{{ $actionTooltips[$pdAction] ?? '' }}">
                                {{ ucfirst(strtolower($pdAction)) }}
                            </span>
                        @endif
                    @else
                        <span class="text-muted small">-</span>
                    @endif
                </td>
                <td class="text-end">
                    @if($pdEz !== null && ($pdEz['proximity_pct'] ?? null) !== null)
                        @php
                            // Same per-window thresholds as the peak-proximity exit-hint email
                            // (finance:peak-proximity-alerts), so "near peak" here means the same
                            // thing as a fired alert: within N% of this window's peak.
                            $nearThreshold = config("alerts.peak_proximity.window_thresholds.{$pKey}")
                                ?? config('alerts.peak_proximity.threshold_pct', 5);
                            $isNearPeak = $pdEz['proximity_pct'] >= -(float) $nearThreshold;
                        @endphp
                        <span class="text-nowrap" data-bs-toggle="tooltip" data-bs-html="true"
                              title="@if($currentPriceLabel){!! $currentPriceLabel !!} current<br>@endif{{ $pLabel }} peak: {!! $peakLabels[$pKey] ?? 'n/a' !!} on {{ $pdPeakDate }}">
                            {!! MoneyFormat::get_formatted_gain('%', $pdEz['proximity_pct']) !!}
                        </span>
                        @if($isNearPeak)
                            {{-- Drop the badge to its own line on smaller screens; keep inline on xl+ (>=1200px) --}}
                            <br class="d-xl-none">
                            {{-- Full opacity only when the position is held; dimmed (like the unowned
                                 action badges) when it is a watch-only symbol, so held near-peak
                                 signals stand out from watchlist noise. --}}
                            <span class="badge bg-success{{ $isOwned ? '' : ' opacity-50' }}"
                                  data-bs-toggle="tooltip"
                                  title="Within {{ $nearThreshold }}% of the {{ $pLabel }} peak {!! $peakLabels[$pKey] ?? 'n/a' !!} on {{ $pdPeakDate }}, the same range that triggers a peak-proximity email.">
                                near peak
                            </span>
                        @endif
                    @else
                        <span class="text-muted small">-</span>
                    @endif
                </td>
                <td class="text-end">
                    @if($pdPnl !== null)
                        @php
                            $isIncomplete = $pdPnl['incomplete'] ?? false;
                            if ($isIncomplete) {
                                $heldPeakDate = !empty($pdPnl['held_peak_date'])
                                    ? \Carbon\Carbon::parse($pdPnl['held_peak_date'])->format('d M Y')
                                    : 'n/a';
                                $pnlTitle = 'If sold at your holding peak '
                                    . MoneyFormat::get_formatted_price_plain($pdPnl['held_peak_eur'])
                                    . '&euro; on ' . $heldPeakDate . ', versus your purchase cost.';
                            } else {
                                $pnlTitle = $pdEz !== null
                                    ? 'If sold at the ' . $pLabel . " peak\n"
                                        . ($peakLabels[$pKey] ?? 'n/a') . ' on ' . $pdPeakDate
                                        . ', versus your purchase cost.'
                                    : '';
                            }
                        @endphp
                        @if($isIncomplete)
                        <i class="fa fa-exclamation-triangle text-warning me-1" aria-hidden="true"
                           data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
                           title="You held this for only part of the {{ $pLabel }}. The {{ $pLabel }} peak was {{ MoneyFormat::get_formatted_price_plain($pdPnl['period_peak_eur']) }}&euro; on {{ $pdPeakDate }}, before you held it. This values your shares at your holding peak {{ MoneyFormat::get_formatted_price_plain($pdPnl['held_peak_eur']) }}&euro; ({{ $heldPeakDate }}), {{ $pdPnl['shortfall_pct'] }}% below the {{ $pLabel }} peak."></i>
                        @endif
                        <span @if($pnlTitle !== '') data-bs-toggle="tooltip" title="{!! $pnlTitle !!}"@endif>
                            <span class="text-nowrap">{!! MoneyFormat::get_formatted_gain('&euro;', $pdPnl['eur'], 0) !!}</span>
                            @if($pdPnl['pct'] !== null)
                                <span class="text-nowrap"><span class="text-muted">(</span>{!! MoneyFormat::get_formatted_gain('%', $pdPnl['pct']) !!}<span class="text-muted">)</span></span>
                            @endif
                        </span>
                    @else
                        <span class="text-muted small">-</span>
                    @endif
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @else
            <span class="text-muted small"
                data-bs-toggle="tooltip"
                title="No VUSA.AS benchmark data; add VUSA.AS to your watchlist">
                No benchmark
            </span>
        @endif
    </div>

</div>
@endif

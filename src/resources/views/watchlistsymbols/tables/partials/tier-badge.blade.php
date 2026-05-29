@php
    use ovidiuro\myfinance2\App\Services\TierCalculationService;
    use ovidiuro\myfinance2\App\Services\TierDecision;

    $cat          = $quoteData['categorization'] ?? null;
    $tier         = $cat['effective_tier'] ?? null;
    $computedTier = $cat['computed_tier'] ?? null;
    $hasOverride  = $cat['has_override'] ?? false;
    $confidence   = $cat['confidence'] ?? TierDecision::CONFIDENCE_HIGH;
    $explanation  = $cat['explanation'] ?? '';
    $isOwned      = !empty($quoteData['open_positions']);

    $tierOrder = [
        TierCalculationService::PLATINUM => 5,
        TierCalculationService::GOLD     => 4,
        TierCalculationService::SILVER   => 3,
        TierCalculationService::BRONZE   => 2,
        TierCalculationService::RUST     => 1,
    ];
    $sortOrder = $tier !== null ? ($tierOrder[$tier] ?? 0) : 0;

    // Only genuinely weak bases are flagged: a position held under 3 months or a
    // market-momentum fallback (low confidence). A settled raw return is not flagged.
    $showWarn = $cat !== null && !$hasOverride
        && $confidence === TierDecision::CONFIDENCE_LOW;
@endphp
<td class="text-nowrap" data-order="{{ $sortOrder }}">
@if ($cat === null)
    <span class="text-muted small">-</span>
@elseif ($tier === null)
    <span class="badge bg-light text-dark border"
        data-bs-toggle="tooltip"
        title="{{ $explanation }}">
        Unrated
    </span>
@else
    @php
        $badgeClass = TierCalculationService::tierBadgeClass($tier);
        $isOrange   = $tier === TierCalculationService::BRONZE;
        $label      = TierCalculationService::tierLabel($tier);
    @endphp
    <span class="badge {{ $badgeClass }}"
        @if($isOrange) style="background-color:#e67e22!important;"@endif
        data-bs-toggle="tooltip"
        title="{{ $explanation }}">
        {{ $label }}{{ $hasOverride ? ' ★' : '' }}
    </span>
    @if ($isOwned)
    <span data-bs-toggle="tooltip" title="Edit tier for {{ $symbol }}"
          style="line-height:1;vertical-align:middle;">
        <button type="button"
            class="btn btn-link btn-sm p-0"
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
    @if ($showWarn)
    <span data-bs-toggle="tooltip"
          title="{{ $explanation }}"
          style="line-height:1;vertical-align:middle;">
        <i class="fa fa-exclamation-circle fa-xs text-warning" aria-hidden="true"></i>
    </span>
    @endif
    @if ($cat['basis'] === TierDecision::BASIS_MARKET_MOMENTUM && !$isOwned)
    <br><small class="text-muted">market 1Y</small>
    @endif
@endif
</td>

@php
    use ovidiuro\myfinance2\App\Services\MoneyFormat;
    use ovidiuro\myfinance2\App\Services\QuadrantClassifier;
    use ovidiuro\myfinance2\App\Services\TierCalculationService;

    $cat      = $quoteData['categorization'] ?? null;
    $quadrant = $cat['quadrant'] ?? null;
    $action   = $cat['action'] ?? null;
    $relDd    = $cat['relative_drawdown'] ?? null;
    $exitZone = $cat['exit_zone'] ?? null;
    $tier     = $cat['effective_tier'] ?? null;
    $isOwned  = !empty($quoteData['open_positions']);

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

    $quadrantOrder = [
        QuadrantClassifier::STEADY_GROWERS   => 4,
        QuadrantClassifier::VOLATILE_WINNERS => 3,
        QuadrantClassifier::DEAD_WEIGHT      => 2,
        QuadrantClassifier::DANGER_ZONE      => 1,
    ];
    $actionOrder = ['ACCUMULATE' => 4, 'HOLD' => 3, 'REDUCE' => 2, 'EXIT' => 1];

    // Sort by relative drawdown asc (lower is safer)
    $sortOrder = $relDd !== null ? number_format($relDd, 4, '.', '') : '9999';
@endphp

<td class="text-nowrap" data-order="{{ $sortOrder }}">
@if ($quadrant !== null)
    <span class="badge {{ $quadrantColors[$quadrant] ?? 'bg-secondary' }}"
        data-bs-toggle="tooltip"
        title="Rel. drawdown: {{ MoneyFormat::get_formatted_number_plain($relDd, 2) }}x">
        {{ QuadrantClassifier::label($quadrant) }}
    </span>
@elseif ($cat !== null)
    <span class="text-muted small"
        data-bs-toggle="tooltip"
        title="No VUSA.AS benchmark data; add VUSA.AS to your watchlist">
        No benchmark
    </span>
@else
    <span class="text-muted small">-</span>
@endif
@if ($relDd !== null)
    <br><small class="text-muted">{{ MoneyFormat::get_formatted_number_plain($relDd, 2) }}x risk</small>
@endif

@if ($action !== null && $isOwned)
    <br>
    <span class="badge {{ $actionColors[$action] ?? 'bg-secondary' }} mt-1">
        {{ $action }}
    </span>
@endif

@if ($exitZone !== null && $isOwned
    && in_array($tier, [TierCalculationService::BRONZE, TierCalculationService::RUST]))
    <br>
    @if ($exitZone['in_zone'])
        <span class="badge bg-success mt-1"
            data-bs-toggle="tooltip"
            title="Within 15% of 2-year peak ({{ $exitZone['peak_price_date'] }})">
            In exit zone
        </span>
    @else
        <span class="badge bg-light text-dark border mt-1"
            data-bs-toggle="tooltip"
            title="2-year peak: {{ $exitZone['peak_price_date'] }}">
            {{ MoneyFormat::get_formatted_number_plain($exitZone['proximity_pct'] ?? 0, 1) }}% from zone
        </span>
    @endif
@endif
</td>

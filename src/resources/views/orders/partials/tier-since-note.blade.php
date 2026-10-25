{{-- On an open order with a saved reading: how today's reading differs, in an amber note, when the tier in use has
     changed since the order was created. $snapshot: the order's TierSnapshot; $now: today's live check. --}}
@php
    use ovidiuro\myfinance2\App\Services\OrderTierWarnings;
    use ovidiuro\myfinance2\App\Services\TierCalculationService;

    $savedTier = $snapshot->effective_tier;
    $nowTier   = $now['reading']['effective_tier'] ?? null;
    $changed   = $now !== null && $nowTier !== null && $nowTier !== $savedTier;
    $nowSerious = OrderTierWarnings::serious($now['warnings'] ?? []);
    $direction = TierCalculationService::tierRank($nowTier) > (TierCalculationService::tierRank($savedTier) ?? -1)
        ? 'up' : 'down';
@endphp
@if($changed && ($order === null || $order->isOpen()))
<div class="alert alert-warning mb-2" role="status">
    <strong>Since you created it:</strong> the tier in use is now
    @include('myfinance2::orders.partials.tier-mini-badge', ['tier' => $nowTier])
    @if($savedTier !== null)({{ $direction }} from {{ TierCalculationService::tierLabel($savedTier) }})@endif.
    @if(!empty($nowSerious))
        Today's reading would raise {{ count($nowSerious) }}
        {{ count($nowSerious) === 1 ? 'serious warning' : 'serious warnings' }}
        ({{ strtolower(implode(', ', array_column($nowSerious, 'label'))) }}).
    @else
        Today's reading would raise no serious warning.
    @endif
    The order is still open: cancel it at the broker if you changed your mind.
</div>
@endif

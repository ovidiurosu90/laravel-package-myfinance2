{{-- One line with the verdict of a trade's tier reading, under the reading on the trade form. $verdict, $warnings,
     $kept, $order: as in tier-reading. --}}
@php
    use ovidiuro\myfinance2\App\Services\OrderTierWarnings;
    use ovidiuro\myfinance2\App\Services\TierVerdictPresenter;

    [$icon, $color] = TierVerdictPresenter::icon((string) $verdict);
    $serious   = OrderTierWarnings::serious($warnings);
    $names     = fn (array $list) => strtolower(implode('; ', array_column($list, 'label')));
    $confirmed = $order !== null && $kept?->acknowledged_at !== null;
@endphp
<div class="small mt-2" data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide"
    title="{{ implode(' ', array_map(fn ($w) => $w['label'] . ': ' . $w['text'], $warnings)) }}">
    <i class="fa {{ $icon }} {{ $color }} fa-fw" aria-hidden="true"></i>
    <span class="text-muted">
        @if(!empty($serious))
            This trade went against the tiers: {{ $names($serious) }}@if(count($serious) < count($warnings));
                also {{ $names(OrderTierWarnings::soft($warnings)) }}@endif.
            @if($confirmed) Confirmed when order #{{ $order->id }} was created. @endif
        @elseif(!empty($warnings))
            This trade raised soft warnings only: {{ $names($warnings) }}.
        @else
            No tier warning (not the same as the tiers endorsing the trade).
        @endif
    </span>
</div>

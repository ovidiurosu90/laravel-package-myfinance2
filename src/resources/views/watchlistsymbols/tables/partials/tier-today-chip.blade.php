{{-- Outlined "today" badge next to the settled (effective) tier, shown only when today's tier differs.
     $chip is CategorizationTierHistory::todayChip(): ['tier', 'label', 'title']. $compact shows the
     initial only (table column, health card rows); the tooltip names the tier either way. --}}
@php use ovidiuro\myfinance2\App\Services\TierCalculationService; @endphp
@if(!empty($chip))
    @php
        $chipText = empty($compact) ? $chip['label'] : TierCalculationService::tierInitial($chip['tier']);
    @endphp
    @if(empty($compact))<span class="text-muted">· today</span>@endif
    <span class="badge bg-transparent text-body fw-normal{{ empty($compact) ? '' : ' me-1' }}"
        style="border:2px solid {{ TierCalculationService::tierColor($chip['tier']) }};"
        data-bs-toggle="tooltip" title="{{ $chip['title'] }}">{{ $chipText }}</span>
@endif

{{-- A tier badge in the approach list: the initial ($full: the full name), or a muted dash without a tier. --}}
@php use ovidiuro\myfinance2\App\Services\TierCalculationService; @endphp
@if($tier !== null)
    <span class="badge {{ TierCalculationService::tierBadgeClass($tier) }}"
        style="min-width:1.6em;@if($tier === TierCalculationService::BRONZE)background-color:#e67e22!important;@endif"
        >{{ $full ? TierCalculationService::tierLabel($tier) : TierCalculationService::tierInitial($tier) }}</span>
@else
    <span class="badge bg-transparent text-muted border" style="min-width:1.6em">&ndash;</span>
@endif

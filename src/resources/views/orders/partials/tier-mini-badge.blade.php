{{-- A small tier badge (the "now" chip, the notes), the watchlist's colours. $tier: a TierCalculationService tier. --}}
@php use ovidiuro\myfinance2\App\Services\TierCalculationService; @endphp
@if(!empty($tier))<span class="badge {{ TierCalculationService::tierBadgeClass($tier) }}"
    style="font-size:0.7em;@if($tier === TierCalculationService::BRONZE)background-color:#e67e22!important;@endif"
    >{{ TierCalculationService::tierLabel($tier) }}</span>@endif

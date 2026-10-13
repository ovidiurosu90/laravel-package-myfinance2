{{-- Fixed-width tier initial for the approach comparison, so the columns line up; a warning sign when the
     approach gives no tier, which should not happen for an owned symbol. --}}
@php use ovidiuro\myfinance2\App\Services\TierCalculationService; @endphp
@if($tier !== null)
<span class="badge {{ TierCalculationService::tierBadgeClass($tier) }}"
    style="min-width:1.6em;@if($tier === TierCalculationService::BRONZE)background-color:#e67e22!important;@endif"
    data-bs-toggle="tooltip" title="{{ $title }}">{{ TierCalculationService::tierInitial($tier) }}</span>
@else
<span class="d-inline-block text-center" style="min-width:1.6em"><i
    class="fa fa-exclamation-triangle text-warning" aria-hidden="true" data-bs-toggle="tooltip"
    data-bs-custom-class="tooltip-wide"
    title="No tier: unexpected for an owned symbol, so it is missing from this approach's allocation. Every
        holding should get a figure, if only a stand-in (your own return); check its price history and trades,
        and fix the data."></i></span>
@endif

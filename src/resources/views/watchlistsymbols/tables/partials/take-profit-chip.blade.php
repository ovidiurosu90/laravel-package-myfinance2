{{-- The weak-tier-in-profit chip, one look everywhere it shows: the row's Tier History line (next to the Chart
     button), the table's Tier column, the peak-proximity inbox and alerts page (list and the Active view's note);
     not the Portfolio Health card (as the range-bound chip). $takeProfit is WeakTierProfit::detect() (or the copy
     stored with a take-profit alert): 'label', 'action', 'title'. $compact shows the action only (the table's Tier
     column); the tooltip explains it either way. --}}
@if(!empty($takeProfit))
    <span class="badge bg-success-subtle text-success-emphasis border border-success fw-normal text-nowrap"
        data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" title="{{ $takeProfit['title'] }}"><i
        class="fas fa-sack-dollar fa-xs me-1" aria-hidden="true"></i>@if(empty($compact)){{ $takeProfit['label'] }}:
        @endif<span class="fw-semibold">{{ $takeProfit['action'] }}</span></span>
@endif

{{-- The range-bound chip, one look everywhere it shows: the row's Tier History line (next to the Chart button), the
     table's Tier column, the peak-proximity inbox and alerts page (list and the Active view's note); not the
     Portfolio Health card (the user's choice). $range is
     RangeBoundPattern::detect() (or the copy stored with a range-bound alert): 'label', 'action', 'title'.
     $compact shows the action only (the table's Tier column); the tooltip explains it either way. --}}
@if(!empty($range))
    <span class="badge bg-info-subtle text-info-emphasis border border-info fw-normal text-nowrap"
        data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" title="{{ $range['title'] }}"><i
        class="fas fa-arrows-up-down fa-xs me-1" aria-hidden="true"></i>@if(empty($compact)){{ $range['label'] }}:
        @endif<span class="fw-semibold">{{ $range['action'] }}</span></span>
@endif

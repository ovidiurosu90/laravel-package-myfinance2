{{-- The near-peak chip, one look everywhere it shows: the row's Tier History line (next to the Chart button), the
     table's Tier column and the peak-proximity inbox; not the Portfolio Health card (as the range-bound and TAKE
     PROFIT chips). $nearPeak is NearPeakReading::detect() (or the copy stored with a near-peak alert): 'label',
     'action', 'title'. A held position near a window's peak on a weak tier (the alert emails) shows the quadrant's
     action; without one (a strong tier, or the 3M window alone: context only) it shows the label alone, dimmed, as a
     watch-only tier signal. $compact shows the action only (the label when there is none); the tooltip explains it
     either way. --}}
@if(!empty($nearPeak))
    @php
        $npAction = $nearPeak['action'] ?? null;
        // The words before the action: the label alone without one, "Label: " in full, nothing when compact.
        $npLabel  = $npAction === null ? $nearPeak['label'] : (empty($compact) ? $nearPeak['label'] . ': ' : '');
    @endphp
    <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary fw-normal
        text-nowrap{{ $npAction === null ? ' opacity-50' : '' }}"
        data-bs-toggle="tooltip" data-bs-custom-class="tooltip-wide" title="{{ $nearPeak['title'] }}"><i
        class="fas fa-mountain fa-xs me-1" aria-hidden="true"></i>{{ $npLabel }}{{--
        --}}@if($npAction !== null)<span class="fw-semibold">{{ $npAction }}</span>@endif</span>
@endif

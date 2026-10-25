{{-- Opens the tier history chart (tier-history-modal) for the reading's symbol: a labelled button, so it reads as
     something to click. --}}
@if(($history ?? null) !== null)
    <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 ms-1 text-nowrap"
        data-symbol="{{ $symbol }}" data-bs-toggle="modal"
        data-bs-target="#tier-history-modal" aria-label="Open the tier history chart for {{ $symbol }}">
        <i class="fas fa-chart-line me-1" aria-hidden="true"></i>Chart
    </button>
@endif

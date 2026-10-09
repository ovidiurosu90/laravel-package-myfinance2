@if(!empty($snapshotStale ?? null))
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    <h6 class="alert-heading mb-2">
        <i class="fa fa-clock-o me-1"></i>
        Stored overview data is stale
        <small class="fw-normal ms-1">
            the background job that refreshes it every minute may have stopped
        </small>
    </h6>
    <div class="small">
        Last refreshed {{ $snapshotStale['age_human'] }} ago ({{ $snapshotStale['taken_at_formatted'] }};
        expected within {{ $snapshotStale['threshold_human'] }}). The headers and the last chart point
        still use this page's live prices, but the earlier chart points, the movers and the
        alerts depend on that job, and the reconciliation check below compares against its
        last run.
    </div>
</div>
<div class="clearfix mb-3"></div>
@endif

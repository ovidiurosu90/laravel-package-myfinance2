{{--
    The per-day tier ribbon on the Tier line of /watchlist-symbols: one segment per run of consecutive trading
    days in the same tier over the last 12 months, oldest on the left (TierHistoryPresenter::forRow). Hovering
    shows the run and the time in each tier in one Bootstrap tooltip that follows the mouse
    (scripts/tier-history-ribbon); clicking opens the tier history chart, like the Chart button next to it.
--}}
@php
    $th = $cat['tier_history'] ?? null;
@endphp
@if($th !== null && !empty($th['ribbon']))
    <span class="tier-history-strip tier-ribbon" role="button" style="width:200px;cursor:pointer;"
        data-symbol="{{ $symbol }}" data-bs-toggle="modal" data-bs-target="#tier-history-modal"
        data-note="{{ $th['ribbon_note'] }}">
        @foreach($th['ribbon'] as $run)
            <span style="width:{{ $run['width_pct'] }}%;background-color:{{ $run['color'] }};"
                data-title="{{ $run['title'] }}"></span>
        @endforeach
    </span>
@endif

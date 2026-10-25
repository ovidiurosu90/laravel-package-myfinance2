{{--
    The per-day tier ribbon on the Tier History line (tier-reading): one segment per run of consecutive trading days in
    the same tier over the last 12 months, oldest on the left (TierHistoryPresenter::forRow). Hovering shows the run
    and the time in each tier in one Bootstrap tooltip that follows the mouse (scripts/tier-history-ribbon); clicking
    opens the tier history chart, like the Chart button next to it. $history is the reading's 'history'.
--}}
@if(!empty($history['ribbon']))
    <span class="tier-history-strip tier-ribbon" role="button" style="width:200px;cursor:pointer;"
        data-symbol="{{ $symbol }}" @if(empty($noChart)) data-bs-toggle="modal" data-bs-target="#tier-history-modal"
        @endif data-note="{{ $history['ribbon_note'] }}">
        @foreach($history['ribbon'] as $run)
            <span style="width:{{ $run['width_pct'] }}%;background-color:{{ $run['color'] }};"
                data-title="{{ $run['title'] }}"></span>
        @endforeach
    </span>
@endif

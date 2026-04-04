<div class="card card-default">
    <div class="card-header">
        <div class="float-right d-flex align-items-center gap-2">
            <div id="alerts-view-toggle" class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-primary" data-view="active">Active</button>
                <button type="button" class="btn btn-outline-secondary" data-view="all">All</button>
            </div>
            <a href="{{ route('myfinance2::price-alerts.history') }}"
               class="btn btn-outline-secondary btn-sm"
               data-bs-toggle="tooltip"
               title="View notification history">
                <i class="fa fa-fw fa-history" aria-hidden="true"></i> History
            </a>
            <button type="button"
                    class="btn btn-outline-info btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#suggest-alerts-modal">
                <i class="fa fa-fw fa-magic" aria-hidden="true"></i> Suggest
            </button>
            <a href="{{ route('myfinance2::price-alerts.create') }}"
               class="btn btn-outline-success btn-sm"
               data-bs-toggle="tooltip"
               title="Create a new Price Alert">
                <i class="fa fa-fw fa-plus-circle" aria-hidden="true"></i> Create Alert
            </a>
        </div>
        {!! trans('myfinance2::alerts.titles.dashboard') !!}
    </div>
    <div class="card-body p-0">
        <div class="p-3">
            @include('myfinance2::alerts.tables.items-table')
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span id="card_title">
                {!! trans('myfinance2::orders.titles.dashboard') !!}
            </span>
            <div class="float-right d-flex align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group" id="orders-view-toggle">
                    <button type="button" data-view="active"
                        class="btn btn-sm {{ $view === 'active' ? 'btn-primary' : 'btn-outline-secondary' }}">
                        Active
                    </button>
                    <button type="button" data-view="all"
                        class="btn btn-sm {{ $view === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">
                        All
                    </button>
                </div>
                <a class="btn btn-sm" href="{{ route('myfinance2::orders.create') }}">
                    <i class="fa fa-fw fa-plus" aria-hidden="true"></i>
                    {!! trans('myfinance2::general.buttons.create') !!}
                </a>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        @include('myfinance2::orders.tables.items-table')
    </div>
</div>

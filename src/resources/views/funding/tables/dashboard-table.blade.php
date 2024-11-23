<div class="card">
    <div class="card-header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span id="card_title">
                {!! trans('myfinance2::funding.titles.dashboard') !!}
            </span>
            <div class="float-right">
                <a class="btn btn-sm" href="{{ route('myfinance2::ledger-transactions.create') }}" data-bs-toggle="tooltip" title="{{ trans('myfinance2::general.tooltips.create-item', ['type' => 'Ledger Transaction']) }}">
                    <i class="fa fa-fw fa-plus" aria-hidden="true"></i>
                    {!! trans('myfinance2::general.buttons.create') !!}
                </a>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        @include('myfinance2::funding.tables.dashboard-items-table')
    </div>
</div>


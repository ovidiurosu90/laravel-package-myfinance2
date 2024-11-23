<div class="card">
    <div class="card-header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span id="card_title">
                {!! trans('myfinance2::dividends.titles.dashboard') !!}
            </span>
            <div class="float-right">
                <a class="btn btn-sm" href="{{ route('myfinance2::dividends.create') }}">
                    <i class="fa fa-fw fa-plus" aria-hidden="true"></i>
                    {!! trans('myfinance2::general.buttons.create') !!}
                </a>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        @include('myfinance2::dividends.tables.items-table')
    </div>
</div>


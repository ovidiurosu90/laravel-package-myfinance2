<div class="card">
    <div class="card-header">
        <span id="card_title" class="align-middle">
            {!! trans('myfinance2::accounts.titles.dashboard') !!}
        </span>
        <div class="float-right">
            <a class="btn btn-sm" href="{{ route('myfinance2::accounts.create') }}">
                <i class="fa fa-fw fa-plus" aria-hidden="true"></i>
                {!! trans('myfinance2::general.buttons.create') !!}
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        @include('myfinance2::accounts.tables.items-table')
    </div>
</div>


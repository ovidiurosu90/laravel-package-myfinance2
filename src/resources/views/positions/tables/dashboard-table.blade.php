@if(empty($groupedItems))

<div class="card">
    <div class="card-header">
        <div style="display: flex; justify-content: space-between;
                    align-items: center;">
            <span id="card_title">
                {!! trans('myfinance2::positions.titles.no-open-positions') !!}
            </span>
            <div class="float-right">
                <a class="btn btn-sm"
                    href="{{ route('myfinance2::trades.create') }}"
                    data-bs-toggle="tooltip"
                    title="{{ trans('myfinance2::general.tooltips.create-item',
                                    ['type' => 'Trade']) }}">
                    <i class="fa fa-fw fa-plus" aria-hidden="true"></i>
                    {!! trans('myfinance2::general.buttons.create') !!}
                </a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <span>No Data</span>
    </div>
</div>

@endif

@foreach($groupedItems as $accountId => $items)

<div class="card">
    <div class="card-header">
        <div style="display: flex; justify-content: space-between;
                    align-items: center;">
            <span id="card_title">
                {{ trans('myfinance2::positions.titles.open-positions') }} -
                {{ $accountData[$accountId]['accountModel']->name }}
                ({!! $accountData[$accountId]['accountModel']
                        ->currency->display_code !!})
            </span>
            <div class="float-right">
                <a class="btn btn-sm"
                    href="{{ route('myfinance2::trades.create') }}"
                    data-bs-toggle="tooltip"
                    title="{{ trans('myfinance2::general.tooltips.create-item',
                                    ['type' => 'Trade']) }}">
                    <i class="fa fa-fw fa-plus" aria-hidden="true"></i>
                    {!! trans('myfinance2::general.buttons.create') !!}
                </a>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        @include('myfinance2::positions.tables.dashboard-items-table')
    </div>
</div>

<div class="clearfix mb-4"></div>

@endforeach


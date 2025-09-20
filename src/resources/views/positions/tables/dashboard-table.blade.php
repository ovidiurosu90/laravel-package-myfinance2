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

@if(!empty($groupedItems))
<div class="card">
    <div class="card-header">
        <div style="display: flex; justify-content: space-between;
                    align-items: center;">
            <span id="card_title">
                {{ trans('myfinance2::positions.titles.user-overview') }}
            </span>
            <div class="float-right">
                <a id="user-overview-title" class="btn btn-sm" href="#user-overview"
                    aria-expanded="true" aria-controls="user-overview"
                    data-bs-toggle="collapse" title="Collapse">
                    <i class="fa fa-chevron-down pull-right"></i>
                </a>
            </div>
        </div>
    </div>
    <div id="user-overview" class="collapse show"
        aria-labelledby="user-overview-title">
        <div class="card-body">
            @include('myfinance2::positions.user-overview')
        </div>
    </div>
</div>
<div class="clearfix mb-4"></div>
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

@if(!empty($groupedItems))
    @include('myfinance2::positions.scripts.user-overview-graph')
    @include('myfinance2::positions.scripts.account-overview-graphs')
    @include('myfinance2::general.scripts.quote-price-graphs')
@endif


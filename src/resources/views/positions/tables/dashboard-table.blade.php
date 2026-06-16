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

@else

<div class="card">
    <div class="card-header">
        <div class="d-flex align-items-center gap-3">
            <span id="card_title" class="text-nowrap">
                {{ trans('myfinance2::positions.titles.user-overview') }}
            </span>
            <div id="user-overview-summary"
                class="align-items-center gap-2 flex-grow-1 overflow-hidden d-none justify-content-end"
                style="display: flex;">
                <span id="uos-eurusd" class="text-muted text-nowrap"></span>
                <span class="text-muted">·</span>
                <span id="uos-cost" class="text-nowrap fw-semibold"></span>
                <span id="uos-mvalue" class="text-nowrap fw-semibold"></span>
                <span class="text-muted">=</span>
                <span id="uos-change" class="text-nowrap fw-semibold"></span>
                <span class="text-muted">·</span>
                <span id="uos-cash" class="text-nowrap fw-semibold"></span>
            </div>
            <div class="ms-auto flex-shrink-0">
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
<div class="clearfix mb-3"></div>

@if(!is_null($moversData ?? null))
    @include('myfinance2::positions.partials.movers')
    <div class="clearfix mb-3"></div>
@endif

@include('myfinance2::positions.partials.dip-buying-panel')
@endif

@foreach($groupedItems as $accountId => $items)

<div class="card">
    <div class="card-header">
        <div class="d-flex align-items-center gap-3">
            <span class="text-nowrap">
                {{ trans('myfinance2::positions.titles.open-positions') }} -
                {{ $accountData[$accountId]['accountModel']->name }}
                ({!! $accountData[$accountId]['accountModel']->currency->display_code !!})
            </span>
            {{-- DISABLED: was d-none by default; removed to keep summary always visible --}}
            <div id="account-overview-summary-{{ $accountId }}"
                class="align-items-center gap-2 flex-grow-1 overflow-hidden
                    justify-content-end"
                style="display:flex;">
                <span id="aos-cost-{{ $accountId }}" class="text-nowrap fw-semibold"></span>
                <span id="aos-mvalue-{{ $accountId }}" class="text-nowrap fw-semibold"></span>
                <span class="text-muted">=</span>
                <span id="aos-change-{{ $accountId }}" class="text-nowrap fw-semibold"></span>
                <span class="text-muted">·</span>
                <span id="aos-cash-{{ $accountId }}" class="text-nowrap fw-semibold"></span>
            </div>
            <div class="ms-auto flex-shrink-0">
                <a id="account-overview-title-{{ $accountId }}" class="btn btn-sm"
                    href="#account-overview-{{ $accountId }}"
                    aria-expanded="true"
                    aria-controls="account-overview-{{ $accountId }}"
                    data-bs-toggle="collapse" title="Collapse">
                    <i class="fa fa-chevron-down pull-right"></i>
                </a>
            </div>
        </div>
    </div>
    <div id="account-overview-{{ $accountId }}" class="collapse show"
        aria-labelledby="account-overview-title-{{ $accountId }}">
        <div class="card-body p-0">
            @include('myfinance2::positions.tables.dashboard-items-table')
        </div>
    </div>
</div>

<div class="clearfix mb-3"></div>

@endforeach

@if(!empty($groupedItems))
    @include('myfinance2::positions.scripts.user-overview-graph')
    @include('myfinance2::positions.scripts.account-overview-graphs')
    @include('myfinance2::general.scripts.quote-price-graphs')
    @include('myfinance2::general.modals.symbol-chart-modal')
    @include('myfinance2::general.scripts.symbol-chart-modal')
@endif


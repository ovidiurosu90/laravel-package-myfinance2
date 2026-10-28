@extends('myfinance2::layouts.app')

@section('myfinance2_title')
    {{ trans('myfinance2::general.titles.create-item', ['type' => 'Order']) }}
@endsection

@section('myfinance2_styles')
    @include('myfinance2::general.partials.styles')
@endsection

@section('myfinance2_content')

    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card card-post" id="post_card">
                    <div class="card-header">
                        {!! trans('myfinance2::general.titles.create-item', ['type' => 'Order']) !!}
                        <div class="pull-right">
                            <a href="{{ route('myfinance2::orders.index') }}"
                                class="btn btn-outline-secondary btn-sm float-right"
                                data-bs-toggle="tooltip" data-bs-placement="left"
                                title="Back to Orders">
                                <i class="fa fa-fw fa-reply-all" aria-hidden="true"></i>
                                Back to Index
                            </a>
                        </div>
                    </div>

                    @if ($duplicateOrders->count() > 0)
                    <div class="card-body pb-0">
                        <div class="alert alert-warning" role="alert">
                            <strong>Warning:</strong>
                            The symbol <strong>{{ $symbol }}</strong> already has
                            {{ $duplicateOrders->count() }} open
                            {{ $duplicateOrders->count() == 1 ? 'order' : 'orders' }}:
                            <ul class="mb-0 mt-1">
                                @foreach ($duplicateOrders as $dupOrder)
                                <li>
                                    <a href="{{ route('myfinance2::orders.edit', $dupOrder->id) }}">
                                        Order #{{ $dupOrder->id }}
                                    </a>
                                    — {{ $dupOrder->status }} {{ $dupOrder->getShortLabel() }}
                                </li>
                                @endforeach
                            </ul>
                            <small class="d-block mt-1">
                                You can proceed anyway to create a new order.
                            </small>
                        </div>
                    </div>
                    @endif

                    {{-- The tier warnings and reading (scripts/tier-reading), then the price alerts --}}
                    <div id="tier-reading-wrapper" class="mx-3 mt-3"></div>
                    <div id="open-alerts-banner-wrapper" class="mx-3">
                        @include('myfinance2::orders.partials.open-alerts-banner')
                    </div>
                    @include('myfinance2::orders.forms.create-item-form')
                    @include('myfinance2::watchlistsymbols.tables.partials.tier-history-modal')
                    @include('myfinance2::orders.partials.tier-confirm-modal', ['isEdit' => false])
                </div>
            </div>
        </div>
    </div>

@endsection

@section('myfinance2_scripts')
    @include('myfinance2::orders.scripts.selectize-item')
    @include('myfinance2::orders.scripts.placed-at-picker')
    @include('myfinance2::general.scripts.tooltips')
    @include('myfinance2::general.scripts.available-quantity')
    @include('myfinance2::orders.scripts.banner')
    @include('myfinance2::orders.scripts.finance')
    @include('myfinance2::general.scripts.symbol-chart-panel')
    @include('myfinance2::orders.scripts.open-alerts-banner', [
        'openAlertsFetchUrl' => route('myfinance2::orders.open-alerts-for-symbol'),
    ])
    @include('myfinance2::orders.scripts.tier-reading', [
        'tierReadingUrl' => route('myfinance2::orders.tier-reading'),
        'tierOrderId'    => null,
    ])
    @include('myfinance2::watchlistsymbols.scripts.tier-history-chart')
    @include('myfinance2::watchlistsymbols.scripts.tier-history-ribbon')
@endsection

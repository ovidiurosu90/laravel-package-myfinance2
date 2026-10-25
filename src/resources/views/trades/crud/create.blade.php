@extends('layouts.app')

@section('template_title'){{ trans('myfinance2::general.titles.create-item', ['type' => 'Trade']) }}@endsection

@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection

@section('content')

    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card card-post" id="post_card">
                    <div class="card-header">
                        {!! trans('myfinance2::general.titles.create-item', ['type' => 'Trade']) !!}
                        <div class="pull-right">
                            <a href="{{ url('trades') }}" class="btn btn-outline-secondary btn-sm float-right" data-bs-toggle="tooltip" data-bs-placement="left" title="Back to Trades Dashboard">
                                <i class="fa fa-fw fa-reply-all" aria-hidden="true"></i>
                                Back to Index
                            </a>
                        </div>
                    </div>
                    @if (!empty($linkedOrder))
                    <div class="card-body pb-0">
                        <div class="alert alert-info py-2" role="alert">
                            <strong>Linked to Order #{{ $linkedOrder->id }}</strong>
                            — {{ $linkedOrder->getShortLabel() }}
                            <small class="d-block mt-1">
                                This trade will be automatically linked to this order when saved.
                            </small>
                        </div>
                    </div>
                    @endif

                    {{-- The tier reading the trade is saved with (scripts/tier-reading) --}}
                    <div id="tier-reading-wrapper" class="mx-3 mt-3"></div>
                    @include('myfinance2::trades.forms.create-item-form')
                    @include('myfinance2::watchlistsymbols.tables.partials.tier-history-modal')
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer_scripts')
    @include('myfinance2::trades.scripts.selectize-item')
    @include('myfinance2::general.scripts.timestamp-picker')
    @include('myfinance2::general.scripts.tooltips')
    @include('myfinance2::general.scripts.available-quantity')
    @include('myfinance2::trades.scripts.finance')
    @include('myfinance2::general.scripts.symbol-chart-panel')
    @include('myfinance2::trades.scripts.tier-reading', [
        'tierReadingUrl' => route('myfinance2::trades.tier-reading'),
        'tierTradeId'    => null,
        'tierOrderId'    => $order_id ?? null,
    ])
    @include('myfinance2::watchlistsymbols.scripts.tier-history-chart')
    @include('myfinance2::watchlistsymbols.scripts.tier-history-ribbon')
@endsection


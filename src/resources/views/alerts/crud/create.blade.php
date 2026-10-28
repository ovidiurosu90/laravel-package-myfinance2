@extends('myfinance2::layouts.app')
@section('myfinance2_title'){{ trans('myfinance2::general.titles.create-item', ['type' => 'Price Alert']) }}@endsection
@section('myfinance2_styles')
    @include('myfinance2::general.partials.styles')
@endsection
@section('myfinance2_content')
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card card-post" id="post_card">
                    <div class="card-header">
                        {!! trans('myfinance2::general.titles.create-item', ['type' => 'Price Alert']) !!}
                        <div class="pull-right">
                            <a href="{{ route('myfinance2::price-alerts.index') }}"
                               class="btn btn-outline-secondary btn-sm float-right"
                               data-bs-toggle="tooltip" data-bs-placement="left"
                               title="Back to Price Alerts">
                                <i class="fa fa-fw fa-reply-all" aria-hidden="true"></i> Back to Index
                            </a>
                        </div>
                    </div>
                    @include('myfinance2::alerts.forms.create-item-form')
                </div>
            </div>
        </div>
    </div>
@endsection
@section('myfinance2_scripts')
    @include('myfinance2::alerts.scripts.selectize-item')
    @include('myfinance2::alerts.scripts.finance')
    @include('myfinance2::alerts.scripts.relative-target')
    @include('myfinance2::general.scripts.symbol-chart-panel')
    @include('myfinance2::alerts.scripts.expires-at-picker')
    @include('myfinance2::general.scripts.tooltips')
@endsection

@extends('layouts.app')
@section('template_title'){{ trans('myfinance2::general.titles.edit-item', ['type' => 'Price Alert']) }}@endsection
@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection
@section('content')
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card card-post" id="post_card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            {!! trans('myfinance2::general.titles.edit-item', ['type' => 'Price Alert']) !!}
                            #{{ $id }}
                            <span class="badge {{ $alertModel->getStatusBadgeClass() }} ms-2">
                                {{ $alertModel->status }}
                            </span>
                        </span>
                        <div>
                            <a href="{{ url('price-alerts') }}"
                               class="btn btn-outline-secondary btn-sm"
                               data-bs-toggle="tooltip" data-bs-placement="left"
                               title="Back to Price Alerts">
                                <i class="fa fa-fw fa-reply-all" aria-hidden="true"></i>
                                Back to Index
                            </a>
                        </div>
                    </div>
                    @include('myfinance2::alerts.forms.edit-item-form')
                </div>
            </div>
        </div>
    </div>
@endsection
@section('footer_scripts')
    @include('myfinance2::alerts.scripts.selectize-item')
    @include('myfinance2::alerts.scripts.expires-at-picker')
    @include('myfinance2::general.scripts.tooltips')
@endsection

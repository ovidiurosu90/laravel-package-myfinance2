@extends('layouts.app')

@section('template_title')
    {{ trans('myfinance2::general.titles.create-item', ['type' => 'Dividend']) }}
@endsection

@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
    @include('myfinance2::general.partials.bs-visibility-css')
    @include('myfinance2::general.partials.selectize-css') {{-- NOTE! We copied it locally to get rid of the zoom warning --}}
@endsection

@section('content')

    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card card-post" id="post_card">
                    <div class="card-header">
                        {!! trans('myfinance2::general.titles.create-item', ['type' => 'Dividend']) !!}
                        <div class="pull-right">
                            <a href="{{ url('dividends') }}" class="btn btn-outline-secondary btn-sm float-right" data-bs-toggle="tooltip" data-placement="left" title="Back to Dividends Dashboard">
                                <i class="fa fa-fw fa-reply-all" aria-hidden="true"></i>
                                Back to Index
                            </a>
                        </div>
                    </div>
                    @include('myfinance2::dividends.forms.create-item-form')
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer_scripts')
    @include('myfinance2::dividends.scripts.selectize-item')
    @include('myfinance2::general.scripts.timestamp-picker')
    @include('myfinance2::general.scripts.tooltips')
    @include('myfinance2::general.scripts.account-currency')

    @include('myfinance2::dividends.scripts.finance')
    @include('myfinance2::dividends.scripts.fee-toggle')
@endsection


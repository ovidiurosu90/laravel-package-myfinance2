@extends('layouts.app')
@section('template_title'){{ trans('myfinance2::general.titles.create-item', ['type' => 'Stock Split']) }}@endsection
@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection
@section('content')
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card card-post" id="post_card">
                    <div class="card-header">
                        {!! trans('myfinance2::general.titles.create-item', ['type' => 'Stock Split']) !!}
                        <div class="pull-right">
                            <a href="{{ route('myfinance2::stock-splits.index') }}"
                               class="btn btn-outline-secondary btn-sm float-right"
                               data-bs-toggle="tooltip" data-bs-placement="left"
                               title="Back to Stock Splits">
                                <i class="fa fa-fw fa-reply-all" aria-hidden="true"></i> Back to Index
                            </a>
                        </div>
                    </div>
                    @include('myfinance2::splits.forms.create-item-form')
                </div>
            </div>
        </div>
    </div>
@endsection
@section('footer_scripts')
    @include('myfinance2::splits.scripts.selectize-item')
    @include('myfinance2::general.scripts.tooltips')
@endsection

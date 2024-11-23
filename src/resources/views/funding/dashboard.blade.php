@extends('layouts.app')

@section('template_title')
    {!! trans('myfinance2::funding.titles.dashboard') !!}
@endsection

@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
    @include('myfinance2::general.partials.bs-visibility-css')
@endsection

@section('content')

    <div class="container-fluid">

        <div class="row">
            <div class="col-sm-12">
                @include('myfinance2::funding.tables.dashboard-table')
            </div>
        </div>

        <div class="clearfix mb-4"></div>

    </div>

@endsection

@section('footer_scripts')
    @include('myfinance2::funding.scripts.datatables')
    @include('myfinance2::general.scripts.tooltips')
@endsection


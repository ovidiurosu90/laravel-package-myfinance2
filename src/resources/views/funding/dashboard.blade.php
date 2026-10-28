@extends('myfinance2::layouts.app')

@section('myfinance2_title'){!!
    trans('myfinance2::funding.titles.dashboard') !!}@endsection

@section('myfinance2_styles')
    @include('myfinance2::general.partials.styles')
@endsection

@section('myfinance2_content')

    <div class="container-fluid">

        <div class="row">
            <div class="col-sm-12">
                @include('myfinance2::funding.tables.dashboard-table')
            </div>
        </div>

        <div class="clearfix mb-4"></div>

    </div>

@endsection

@section('myfinance2_scripts')
    @include('myfinance2::funding.scripts.datatables')
    @include('myfinance2::general.scripts.tooltips')
@endsection


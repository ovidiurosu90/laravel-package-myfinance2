@extends('myfinance2::layouts.app')

@section('myfinance2_title'){!! trans('myfinance2::timeline.titles.dashboard') !!}@endsection

@section('myfinance2_styles')
    @include('myfinance2::general.partials.styles')
@endsection

@section('myfinance2_content')

    <div class="container-fluid">

        <div class="row">
            <div class="col-sm-12">
                <div id="timeline" style="height: 600px;">No Data</div>
            </div>
        </div>

        <div class="clearfix mb-4"></div>

    </div>

@endsection

@section('myfinance2_scripts')
    @include('myfinance2::timeline.scripts.google-charts')
    @include('myfinance2::general.scripts.tooltips')
@endsection


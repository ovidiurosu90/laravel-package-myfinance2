@extends('layouts.app')

@section('template_title'){!! trans('myfinance2::timeline.titles.dashboard') !!}@endsection

@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection

@section('content')

    <div class="container-fluid">

        <div class="row">
            <div class="col-sm-12">
                <div id="timeline" style="height: 600px;">No Data</div>
            </div>
        </div>

        <div class="clearfix mb-4"></div>

    </div>

@endsection

@section('footer_scripts')
    @include('myfinance2::timeline.scripts.google-charts')
    @include('myfinance2::general.scripts.tooltips')
@endsection


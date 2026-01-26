@extends('layouts.app')

@section('template_title'){!! trans('myfinance2::returns.titles.dashboard') !!}@endsection

@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                @include('myfinance2::returns.tables.dashboard-table')
            </div>
        </div>
        <div class="clearfix mb-4"></div>
    </div>
@endsection

@section('footer_scripts')
    @include('myfinance2::general.scripts.tooltips')
    @include('myfinance2::returns.scripts.year-selector')
    @include('myfinance2::returns.scripts.currency-toggle')
    @include('myfinance2::general.modals.confirm-modal', [
        'formTrigger'   => 'confirm-clear-cache-modal',
        'modalClass'    => 'warning',
        'actionBtnIcon' => 'fa-radiation',
        'btnSubmitText' => trans('myfinance2::general.buttons.clear-cache'),
    ])
    @include('myfinance2::returns.scripts.clear-cache')
@endsection


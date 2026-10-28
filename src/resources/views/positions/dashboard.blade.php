@extends('myfinance2::layouts.app')

@section('myfinance2_title'){!! trans('myfinance2::positions.titles.dashboard') !!}@endsection

@section('myfinance2_styles')
    @include('myfinance2::general.partials.styles')
@endsection

@section('myfinance2_content')

    <div class="container-fluid">

        <div class="row">
            <div class="col-sm-12">
                @include('myfinance2::general.partials.stale-quote-alerts')
                @include('myfinance2::positions.partials.snapshot-stale-alert')
                @include('myfinance2::general.partials.reconciliation-alerts')
                @include('myfinance2::positions.tables.dashboard-table')
            </div>
        </div>

        <div class="clearfix mb-4"></div>

        @include('myfinance2::general.modals.confirm-modal',[
            'formTrigger' => 'confirm-delete-modal',
            'modalClass' => 'danger',
            'actionBtnIcon' => 'fa-window-close'
        ])

    </div>

@endsection

@section('myfinance2_scripts')
    @include('myfinance2::general.scripts.confirm-modal', ['formTrigger' => 'confirm-delete-modal'])
    @include('myfinance2::positions.scripts.user-overview')
    @include('myfinance2::positions.scripts.movers')
    @include('myfinance2::positions.scripts.dip-buying-panel')
    @include('myfinance2::positions.scripts.datatables')
    @include('myfinance2::general.scripts.tooltips')
    @include('myfinance2::positions.scripts.countdowns')
    @include('myfinance2::positions.scripts.card-collapse')
@endsection


@extends('myfinance2::layouts.app')

@section('myfinance2_title'){!! trans('myfinance2::home.titles.dashboard') !!}@endsection

@section('myfinance2_styles')
    @include('myfinance2::general.partials.styles')
    @include('myfinance2::home.partials.styles')
@endsection

@section('myfinance2_content')

    <div class="container-fluid">

        <div class="row">
            @include('myfinance2::home.cards.funding')
            @include('myfinance2::home.cards.open-positions')
            @include('myfinance2::home.cards.gains-per-year')
        </div>

        <div class="clearfix mb-2"></div>

        <div class="row">
            @include('myfinance2::home.cards.currency-exchanges')
            @include('myfinance2::home.cards.dividends')
        </div>

        <div class="clearfix mb-2"></div>

        @include('myfinance2::general.modals.confirm-modal',[
            'formTrigger' => 'confirm-delete-modal',
            'modalClass' => 'danger',
            'actionBtnIcon' => 'fa-window-close'
        ])

    </div>

@endsection

@section('myfinance2_scripts')
    @include('myfinance2::home.scripts.selectize-currency-exchanges')
    @include('myfinance2::general.scripts.confirm-modal', ['formTrigger' => 'confirm-delete-modal'])
    @include('myfinance2::general.scripts.tooltips')
@endsection


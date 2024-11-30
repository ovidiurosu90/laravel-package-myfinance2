@extends('layouts.app')

@section('template_title'){!! trans('myfinance2::home.titles.dashboard') !!}@endsection

@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
    @include('myfinance2::home.partials.styles')
@endsection

@section('content')

    <div class="container-fluid">

        <div class="row">
            @include('myfinance2::home.cards.funding')
            @include('myfinance2::home.cards.open-positions')
            @include('myfinance2::home.cards.gains-per-year')
        </div>

        <div class="row">
            @include('myfinance2::home.cards.currency-exchanges')
            @include('myfinance2::home.cards.dividends')
        </div>

        <div class="clearfix mb-4"></div>

        @include('myfinance2::general.modals.confirm-modal',[
            'formTrigger' => 'confirm-delete-modal',
            'modalClass' => 'danger',
            'actionBtnIcon' => 'fa-window-close'
        ])

    </div>

@endsection

@section('footer_scripts')
    @include('myfinance2::home.scripts.selectize-currency-exchanges')
    @include('myfinance2::general.scripts.confirm-modal', ['formTrigger' => 'confirm-delete-modal'])
    @include('myfinance2::general.scripts.tooltips')
@endsection


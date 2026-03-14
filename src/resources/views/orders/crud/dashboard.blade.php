@extends('layouts.app')

@section('template_title'){!! trans('myfinance2::orders.titles.dashboard') !!}@endsection

@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection

@section('content')

    <div class="container-fluid">

        <div class="row">
            <div class="col-sm-12">
                @include('myfinance2::orders.tables.items-card')
            </div>
        </div>

        <div class="clearfix mb-4"></div>

        @include('myfinance2::general.modals.confirm-modal', [
            'formTrigger'   => 'confirm-delete-modal',
            'modalClass'    => 'danger',
            'actionBtnIcon' => 'fa-check',
        ])

        @include('myfinance2::orders.modals.fill-order-modal')
        @include('myfinance2::orders.modals.link-trade-modal')

    </div>

@endsection

@section('footer_scripts')
    @include('myfinance2::general.scripts.confirm-modal', ['formTrigger' => 'confirm-delete-modal'])
    @include('myfinance2::orders.scripts.modals')
    @include('myfinance2::orders.scripts.datatables')
    @include('myfinance2::general.scripts.tooltips')
@endsection

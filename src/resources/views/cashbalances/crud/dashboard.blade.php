@extends('layouts.app')

@section('template_title')
    {!! trans('myfinance2::cashbalances.titles.dashboard') !!}
@endsection

@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
    @include('myfinance2::general.partials.bs-visibility-css')
@endsection

@section('content')

    <div class="container-fluid">

        <div class="row">
            <div class="col-sm-12">
                @include('myfinance2::cashbalances.tables.items-card')
            </div>
        </div>

        <div class="clearfix mb-4"></div>

        @include('myfinance2::general.modals.confirm-modal',[
            'formTrigger' => 'confirm-delete-modal',
            'modalClass' => 'danger',
            'actionBtnIcon' => 'fa-trash-o'
        ])

    </div>

@endsection

@section('footer_scripts')
    @include('myfinance2::general.scripts.confirm-modal', ['formTrigger' => 'confirm-delete-modal'])
    @include('myfinance2::cashbalances.scripts.datatables')
    @include('myfinance2::general.scripts.tooltips')
@endsection


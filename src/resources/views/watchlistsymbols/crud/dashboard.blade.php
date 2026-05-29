@extends('layouts.app')

@section('template_title'){!! trans('myfinance2::watchlistsymbols.titles.dashboard') !!}@endsection

@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection

@section('content')

    <div class="container-fluid">

        <div class="row">
            <div class="col-sm-12">
                @include('myfinance2::watchlistsymbols.tables.partials.health-score-card',
                    ['health_score' => $health_score ?? null])
                @include('myfinance2::watchlistsymbols.tables.partials.quadrant-chart',
                    ['quadrant' => $quadrant ?? null])
                @include('myfinance2::watchlistsymbols.tables.items-card')
            </div>
        </div>

        <div class="clearfix mb-4"></div>

        @include('myfinance2::general.modals.confirm-modal',[
            'formTrigger' => 'confirm-delete-modal',
            'modalClass' => 'danger',
            'actionBtnIcon' => 'fa-trash-o'
        ])

        @include('myfinance2::watchlistsymbols.tables.partials.tier-override-modal')

    </div>

@endsection

@section('footer_scripts')
    @include('myfinance2::general.scripts.confirm-modal', ['formTrigger' => 'confirm-delete-modal'])
    @include('myfinance2::general.scripts.tooltips')
    @include('myfinance2::watchlistsymbols.scripts.datatables')
    @include('myfinance2::watchlistsymbols.scripts.tier-override')
    @include('myfinance2::watchlistsymbols.scripts.health-score-card',
        ['health_score' => $health_score ?? null])
    @include('myfinance2::watchlistsymbols.scripts.quadrant-chart',
        ['quadrant' => $quadrant ?? null])
@endsection

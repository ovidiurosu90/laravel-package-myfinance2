@extends('layouts.app')
@section('template_title'){!! trans('myfinance2::alerts.titles.dashboard') !!}@endsection
@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                @include('myfinance2::alerts.tables.items-card')
            </div>
        </div>
        <div class="clearfix mb-4"></div>
        @include('myfinance2::general.modals.confirm-modal', [
            'formTrigger' => 'confirm-delete-modal',
            'modalClass' => 'danger',
            'actionBtnIcon' => 'fa-check',
        ])
        @include('myfinance2::general.modals.confirm-modal', [
            'formTrigger' => 'confirm-pause-modal',
            'modalClass' => 'warning',
            'actionBtnIcon' => 'fa-check',
        ])
        @include('myfinance2::general.modals.confirm-modal', [
            'formTrigger' => 'confirm-resume-modal',
            'modalClass' => 'success',
            'actionBtnIcon' => 'fa-check',
        ])

        <div class="modal fade" id="suggest-alerts-modal" tabindex="-1"
             aria-labelledby="suggest-alerts-modal-label" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="suggest-alerts-modal-label">
                            <i class="fa fa-fw fa-magic" aria-hidden="true"></i> Suggest Price Alerts
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>
                            This will auto-generate <strong>PRICE_ABOVE</strong> alerts for your
                            open long positions using the following strategy:
                        </p>
                        <ul>
                            <li>
                                Target price: <strong>{{ config('alerts.suggestion_threshold_pct', 3) }}%
                                below the lookback high</strong> for each symbol
                            </li>
                            <li>
                                Lookback window: <strong>2 years</strong> for positions held 2+ years,
                                <strong>1 year</strong> for newer positions
                            </li>
                            <li>
                                Skips symbols that already have an <strong>ACTIVE</strong> or
                                <strong>PAUSED</strong> PRICE_ABOVE alert
                            </li>
                            <li>Currency is inherited from your open trades</li>
                            <li>Notes will record the lookback high price and date</li>
                        </ul>
                        <p class="text-muted small mb-0">
                            <i class="fa fa-fw fa-info-circle" aria-hidden="true"></i>
                            Historical price data is fetched from Yahoo Finance and may take
                            a few seconds per symbol.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary"
                                data-bs-dismiss="modal">
                            <i class="fa fa-fw fa-times" aria-hidden="true"></i> Cancel
                        </button>
                        <form method="POST"
                              action="{{ route('myfinance2::price-alerts.suggest') }}"
                              class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-info">
                                <i class="fa fa-fw fa-magic" aria-hidden="true"></i> Run Suggestions
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('footer_scripts')
    @include('myfinance2::general.scripts.confirm-modal', ['formTrigger' => 'confirm-delete-modal'])
    @include('myfinance2::general.scripts.confirm-modal', ['formTrigger' => 'confirm-pause-modal'])
    @include('myfinance2::general.scripts.confirm-modal', ['formTrigger' => 'confirm-resume-modal'])
    @include('myfinance2::alerts.scripts.datatables')
    @include('myfinance2::general.scripts.tooltips')
@endsection

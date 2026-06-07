@extends('layouts.app')
@section('template_title', 'Peak-Proximity Alerts')
@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-default">
                    <div class="card-header">
                        <div class="float-right d-flex align-items-center gap-2">
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="{{ route('myfinance2::peak-proximity-alerts.index', ['view' => 'active']) }}"
                                   class="btn {{ $view === 'active' ? 'btn-primary' : 'btn-outline-secondary' }}">
                                    Active
                                </a>
                                <a href="{{ route('myfinance2::peak-proximity-alerts.index', ['view' => 'all']) }}"
                                   class="btn {{ $view === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">
                                    All
                                </a>
                            </div>
                            <a href="{{ route('myfinance2::peak-proximity-alerts.history') }}"
                               class="btn btn-outline-secondary btn-sm"
                               data-bs-toggle="tooltip"
                               title="View notification history">
                                <i class="fa fa-fw fa-history" aria-hidden="true"></i> History
                            </a>
                            <a href="{{ url('/watchlist-symbols') }}"
                               class="btn btn-outline-secondary btn-sm"
                               data-bs-toggle="tooltip"
                               title="Open the watchlist symbols dashboard">
                                <i class="fa fa-fw fa-line-chart" aria-hidden="true"></i> Watchlist
                            </a>
                        </div>
                        Peak-Proximity Alerts
                    </div>
                    <div class="card-body p-0">
                        <div class="p-3">
                            <p class="text-muted small">
                                These alerts are off by default. Enable the symbols you want a daily
                                near-peak email for. Disable (pause) the rest. An optional "until" date
                                makes the change temporary and auto-reverts afterwards.
                            </p>

                            <div id="peak-bulk-action-bar" style="display:none"
                                 class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                <span id="peak-bulk-selection-count"
                                      class="text-muted small fw-semibold me-1"></span>
                                <form method="POST" id="peak-bulk-action-form"
                                      class="d-flex align-items-center gap-2 flex-wrap mb-0"
                                      data-enable-url="{{ route('myfinance2::peak-proximity-alerts.enable') }}"
                                      data-disable-url="{{ route('myfinance2::peak-proximity-alerts.disable') }}">
                                    @csrf
                                    <input type="hidden" name="view" value="{{ $view }}">
                                    <label class="small text-muted mb-0 d-flex align-items-center gap-1">
                                        Until
                                        <span class="input-group input-group-sm date" id="peak-until-picker"
                                              style="width: auto;"
                                              data-td-target-input="nearest" data-td-target-toggle="nearest">
                                            <input type="text" name="until" id="peak-bulk-until"
                                                   class="form-control form-control-sm datetimepicker-input"
                                                   style="width: 8rem;"
                                                   data-td-target="#peak-until-picker"
                                                   data-min-date="{{ today()->addDay()->format('Y-m-d') }}"
                                                   data-bs-toggle="tooltip"
                                                   title="Leave blank for a permanent change">
                                            <span class="input-group-text" data-td-target="#peak-until-picker"
                                                  data-td-toggle="datetimepicker" role="button">
                                                <span class="fas fa-calendar"></span>
                                            </span>
                                        </span>
                                    </label>
                                    <button type="button" id="peak-bulk-enable-btn" data-bulk-status="enable"
                                            class="btn btn-sm btn-outline-success" disabled>
                                        <i class="fa fa-fw fa-bell" aria-hidden="true"></i> Enable
                                    </button>
                                    <button type="button" id="peak-bulk-disable-btn" data-bulk-status="disable"
                                            class="btn btn-sm btn-outline-secondary" disabled>
                                        <i class="fa fa-fw fa-bell-slash" aria-hidden="true"></i> Disable
                                    </button>
                                    <button type="button" id="peak-bulk-clear"
                                            class="btn btn-sm btn-link text-secondary p-0 ms-1"
                                            data-bs-toggle="tooltip" title="Clear selection" disabled>
                                        <i class="fa fa-fw fa-times" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </div>

                            @include('myfinance2::peakproximityalerts.tables.items-table')
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="clearfix mb-4"></div>
    </div>
@endsection
@section('footer_scripts')
    @include('myfinance2::peakproximityalerts.scripts.datatables')
    @include('myfinance2::peakproximityalerts.scripts.until-picker')
    @include('myfinance2::general.scripts.tooltips')
@endsection

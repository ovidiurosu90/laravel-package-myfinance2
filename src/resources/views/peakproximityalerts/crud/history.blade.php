@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@extends('layouts.app')
@section('template_title', 'Peak-Proximity Alert History')
@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-default">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            Peak-Proximity Alert History
                            @if ($symbol)
                                <span class="badge bg-secondary ms-2">{{ $symbol }}</span>
                            @endif
                        </span>
                        <div class="d-flex gap-2 flex-wrap align-items-center">
                            @include('myfinance2::peakproximityalerts.partials.subnav', ['active' => 'history'])
                            <form method="POST"
                                  action="{{ route('myfinance2::peak-proximity-alerts.history.clear-today') }}"
                                  class="mb-0"
                                  onsubmit="return confirm('Clear all of today\'s alert records and dismissals for your account? Those symbols will be able to alert again today.');">
                                @csrf
                                <button type="submit" class="btn btn-outline-warning btn-sm"
                                        data-bs-toggle="tooltip"
                                        title="Delete today's records and dismissals so those symbols can alert again today">
                                    <i class="fa fa-fw fa-eraser" aria-hidden="true"></i> Clear today
                                </button>
                            </form>
                            <form method="POST"
                                  action="{{ route('myfinance2::peak-proximity-alerts.history.rerun') }}"
                                  class="mb-0"
                                  onsubmit="return confirm('Re-run peak-proximity alerts now for your account? This evaluates your enabled symbols and sends any due emails.');">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary btn-sm"
                                        data-bs-toggle="tooltip"
                                        title="Run the alert engine now for your account">
                                    <i class="fa fa-fw fa-refresh" aria-hidden="true"></i> Re-run
                                </button>
                            </form>
                            @if ($symbol)
                                <a href="{{ route('myfinance2::peak-proximity-alerts.history') }}"
                                   class="btn btn-outline-secondary btn-sm"
                                   data-bs-toggle="tooltip" title="Remove the symbol filter">
                                    Show All
                                </a>
                            @endif
                            <a href="{{ url('/watchlist-symbols') }}"
                               class="btn btn-outline-secondary btn-sm"
                               data-bs-toggle="tooltip"
                               title="Open the watchlist symbols dashboard">
                                <i class="fa fa-fw fa-line-chart" aria-hidden="true"></i> Watchlist
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-3">
                            <div id="peak-history-bulk-action-bar" style="display:none"
                                 class="d-flex align-items-center gap-1 flex-wrap">
                                <span id="peak-history-bulk-selection-count"
                                      class="text-muted small fw-semibold me-1"></span>
                                <form method="POST"
                                      action="{{ route('myfinance2::peak-proximity-alerts.history.bulk-action') }}"
                                      id="peak-history-bulk-action-form"
                                      class="d-flex gap-1 flex-wrap mb-0">
                                    @csrf
                                    <input type="hidden" name="action" value="delete">
                                    <button type="button"
                                            id="peak-history-bulk-delete-btn"
                                            class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="tooltip"
                                            title="Delete selected notification records"
                                            disabled>
                                        <i class="fa fa-fw fa-trash" aria-hidden="true"></i> Delete
                                    </button>
                                    <button type="button"
                                            id="peak-history-bulk-clear"
                                            class="btn btn-sm btn-link text-secondary p-0 ms-1"
                                            data-bs-toggle="tooltip"
                                            title="Clear selection"
                                            disabled>
                                        <i class="fa fa-fw fa-times" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped data-table peak-history-table">
                                    <thead>
                                        <tr>
                                            <th class="no-sort no-search" style="width: 1px;">
                                                <input type="checkbox" id="select-all-peak-history"
                                                       title="Select all visible">
                                            </th>
                                            <th>Sent At</th>
                                            <th>Symbol</th>
                                            <th class="text-right text-nowrap">Price</th>
                                            <th>Windows</th>
                                            <th class="text-right text-nowrap">Closest</th>
                                            <th>Peak Dates</th>
                                            <th>Status</th>
                                            <th class="no-sort no-search">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($items as $notif)
                                        <tr>
                                            <td>
                                                <input type="checkbox"
                                                       class="peak-history-row-checkbox"
                                                       value="{{ $notif->id }}">
                                            </td>
                                            <td class="text-nowrap"
                                                data-order="{{ $notif->sent_at ? $notif->sent_at->timestamp : 0 }}">
                                                {{ $notif->sent_at ? $notif->sent_at->timezone(config('app.timezone'))->format('Y-m-d H:i') : '-' }}
                                            </td>
                                            <td>
                                                <a href="https://finance.yahoo.com/quote/{{ $notif->symbol }}"
                                                    target="_blank">
                                                    {{ $notif->symbol }}
                                                </a>
                                            </td>
                                            <td class="text-right text-nowrap">
                                                @if ($notif->current_price !== null)
                                                    {{ MoneyFormat::get_formatted_price_plain((float) $notif->current_price, true) }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-nowrap">{{ $notif->triggered_windows }}</td>
                                            <td class="text-right text-nowrap">
                                                @if ($notif->closest_proximity_pct !== null)
                                                    {{ MoneyFormat::get_formatted_pct((float) $notif->closest_proximity_pct) }}%
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="small text-muted">{{ $notif->peak_dates ?: '-' }}</td>
                                            <td>
                                                @if ($notif->status === 'SENT')
                                                    <span class="badge bg-success">SENT</span>
                                                @else
                                                    <span class="badge bg-danger"
                                                          data-bs-toggle="tooltip"
                                                          title="{{ $notif->error_message }}">
                                                        FAILED
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <form method="POST"
                                                      action="{{ route('myfinance2::peak-proximity-alerts.history.destroy', $notif->id) }}"
                                                      onsubmit="return confirm('Delete this record? The symbol will be able to alert again today.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="btn btn-sm btn-outline-danger w-100"
                                                            data-bs-toggle="tooltip"
                                                            title="Delete record; allows the symbol to alert again today">
                                                        Delete <i class="fa fa-trash-o fa-fw" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('footer_scripts')
    @include('myfinance2::peakproximityalerts.scripts.history-datatables')
    @include('myfinance2::general.scripts.tooltips')
@endsection

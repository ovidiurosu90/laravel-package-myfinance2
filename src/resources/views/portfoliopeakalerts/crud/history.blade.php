@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@extends('layouts.app')
@section('template_title', 'Portfolio Peak Alert History')
@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection
@php
    $metricLbl = ['change_eur' => 'EUR gain', 'change_pct' => 'Return %'];
    $windowLbl = ['6m' => '6M', '1y' => '1Y', '2y' => '2Y'];
    $labelList = function (?string $csv, array $map): string
    {
        if (empty($csv)) {
            return '-';
        }
        $out = array_map(fn ($k) => $map[$k] ?? $k, array_filter(explode(',', $csv)));
        return implode(', ', $out);
    };
@endphp
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-default">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Portfolio Peak Alert History</span>
                        @include('myfinance2::portfoliopeakalerts.partials.subnav', ['active' => 'history'])
                    </div>
                    <div class="card-body p-0">
                        <div class="p-3">
                            @if (session('success'))
                                <div class="alert alert-success">{{ session('success') }}</div>
                            @endif
                            @if (session('error'))
                                <div class="alert alert-danger">{{ session('error') }}</div>
                            @endif

                            <div id="ppa-history-bulk-action-bar" style="display:none"
                                 class="d-flex align-items-center gap-1 flex-wrap">
                                <span id="ppa-history-bulk-selection-count"
                                      class="text-muted small fw-semibold me-1"></span>
                                <form method="POST"
                                      action="{{ route('myfinance2::portfolio-peak-alerts.history.bulk-action') }}"
                                      id="ppa-history-bulk-action-form"
                                      class="d-flex gap-1 flex-wrap mb-0">
                                    @csrf
                                    <input type="hidden" name="action" value="delete">
                                    <button type="button" id="ppa-history-bulk-delete-btn"
                                            class="btn btn-sm btn-outline-danger" disabled>
                                        <i class="fa fa-fw fa-trash" aria-hidden="true"></i> Delete
                                    </button>
                                </form>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped data-table ppa-history-table">
                                    <thead>
                                        <tr>
                                            <th class="no-sort no-search" style="width: 1px;">
                                                <input type="checkbox" id="select-all-ppa-history"
                                                       title="Select all visible">
                                            </th>
                                            <th>Sent At</th>
                                            <th>Metrics</th>
                                            <th>Windows</th>
                                            <th class="text-end text-nowrap">Closest</th>
                                            <th class="text-end text-nowrap">Return %</th>
                                            <th class="text-end text-nowrap">EUR gain</th>
                                            <th class="text-end text-nowrap">VUSA</th>
                                            <th>Status</th>
                                            <th class="no-sort no-search">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($items as $notif)
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="ppa-history-row-checkbox"
                                                       value="{{ $notif->id }}">
                                            </td>
                                            <td class="text-nowrap"
                                                data-order="{{ $notif->sent_at ? $notif->sent_at->timestamp : 0 }}">
                                                {{ $notif->sent_at ? $notif->sent_at->timezone(config('app.timezone'))->format('Y-m-d H:i') : '-' }}
                                            </td>
                                            <td class="text-nowrap">{{ $labelList($notif->triggered_metrics, $metricLbl) }}</td>
                                            <td class="text-nowrap">{{ $labelList($notif->triggered_windows, $windowLbl) }}</td>
                                            <td class="text-end text-nowrap">
                                                @if (!is_null($notif->closest_proximity_pct))
                                                    {{ MoneyFormat::get_formatted_pct((float) $notif->closest_proximity_pct) }}%
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="text-end text-nowrap">
                                                @if (!is_null($notif->change_pct_current))
                                                    {{ MoneyFormat::get_formatted_pct((float) $notif->change_pct_current) }}%
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="text-end text-nowrap">
                                                @if (!is_null($notif->change_eur_current))
                                                    &euro;{{ MoneyFormat::get_formatted_number_plain((float) $notif->change_eur_current, 0) }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="text-end text-nowrap">
                                                @if (!is_null($notif->vusa_change_pct))
                                                    {{ MoneyFormat::get_formatted_pct((float) $notif->vusa_change_pct) }}%
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if ($notif->status === 'SENT')
                                                    <span class="badge bg-success">SENT</span>
                                                @else
                                                    <span class="badge bg-danger" data-bs-toggle="tooltip"
                                                          title="{{ $notif->error_message }}">FAILED</span>
                                                @endif
                                            </td>
                                            <td>
                                                <form method="POST"
                                                      action="{{ route('myfinance2::portfolio-peak-alerts.history.destroy', $notif->id) }}"
                                                      onsubmit="return confirm('Delete this record?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">
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
    @include('myfinance2::portfoliopeakalerts.scripts.history-datatables')
    @include('myfinance2::general.scripts.tooltips')
@endsection

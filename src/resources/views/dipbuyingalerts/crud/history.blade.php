@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@extends('layouts.app')
@section('template_title', 'Dip Buying Plan Alert History')
@section('template_linked_css')
    @include('myfinance2::general.partials.styles')
@endsection
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card card-default">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Dip Buying Plan Alert History</span>
                        @include('myfinance2::dipbuyingalerts.partials.subnav', ['active' => 'history'])
                    </div>
                    <div class="card-body p-0">
                        <div class="p-3">
                            @if (session('success'))
                                <div class="alert alert-success">{{ session('success') }}</div>
                            @endif
                            @if (session('error'))
                                <div class="alert alert-danger">{{ session('error') }}</div>
                            @endif

                            <div id="dip-history-bulk-action-bar" style="display:none"
                                 class="d-flex align-items-center gap-1 flex-wrap">
                                <span id="dip-history-bulk-selection-count"
                                      class="text-muted small fw-semibold me-1"></span>
                                <form method="POST"
                                      action="{{ route('myfinance2::dip-buying-alerts.history.bulk-action') }}"
                                      id="dip-history-bulk-action-form"
                                      class="d-flex gap-1 flex-wrap mb-0">
                                    @csrf
                                    <input type="hidden" name="action" value="delete">
                                    <button type="button" id="dip-history-bulk-delete-btn"
                                            class="btn btn-sm btn-outline-danger" disabled>
                                        <i class="fa fa-fw fa-trash" aria-hidden="true"></i> Delete
                                    </button>
                                </form>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped data-table dip-history-table">
                                    <thead>
                                        <tr>
                                            <th class="no-sort no-search" style="width: 1px;">
                                                <input type="checkbox" id="select-all-dip-history"
                                                       title="Select all visible">
                                            </th>
                                            <th>Sent At</th>
                                            <th>Trigger</th>
                                            <th class="text-end text-nowrap">Eff. DD</th>
                                            <th>Driver</th>
                                            <th class="text-end text-nowrap">Target</th>
                                            <th class="text-end text-nowrap">Deployed</th>
                                            <th class="text-end text-nowrap">Tranche</th>
                                            <th>Verdict</th>
                                            <th>Status</th>
                                            <th class="no-sort no-search">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($items as $notif)
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="dip-history-row-checkbox"
                                                       value="{{ $notif->id }}">
                                            </td>
                                            <td class="text-nowrap"
                                                data-order="{{ $notif->sent_at ? $notif->sent_at->timestamp : 0 }}">
                                                {{ $notif->sent_at ? $notif->sent_at->timezone(config('app.timezone'))->format('Y-m-d H:i') : '-' }}
                                            </td>
                                            <td class="text-nowrap">
                                                @switch($notif->trigger)
                                                    @case('new_episode')
                                                        <span class="badge bg-info">New episode</span>
                                                        @break
                                                    @case('band_deepened')
                                                        <span class="badge bg-primary">Band deepened</span>
                                                        @break
                                                    @case('crossed_behind')
                                                        <span class="badge bg-danger">Crossed behind</span>
                                                        @break
                                                    @case('stall')
                                                        <span class="badge bg-warning text-dark">Stall</span>
                                                        @break
                                                    @default
                                                        {{ $notif->trigger }}
                                                @endswitch
                                            </td>
                                            <td class="text-end text-nowrap">
                                                -{{ MoneyFormat::get_formatted_pct((float) $notif->effective_dd_pct) }}%
                                            </td>
                                            <td>{{ $notif->driver }}</td>
                                            <td class="text-end text-nowrap">{{ $notif->target_pct }}%</td>
                                            <td class="text-end text-nowrap">
                                                &euro;{{ MoneyFormat::get_formatted_number_plain((float) $notif->deployed_eur, 0) }}
                                                ({{ MoneyFormat::get_formatted_pct((float) $notif->deployed_pct) }}%)
                                            </td>
                                            <td class="text-end text-nowrap">
                                                &euro;{{ MoneyFormat::get_formatted_number_plain((float) $notif->suggested_tranche_eur, 0) }}
                                            </td>
                                            <td class="text-nowrap">{{ $notif->verdict }}</td>
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
                                                      action="{{ route('myfinance2::dip-buying-alerts.history.destroy', $notif->id) }}"
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
    @include('myfinance2::dipbuyingalerts.scripts.history-datatables')
    @include('myfinance2::general.scripts.tooltips')
@endsection

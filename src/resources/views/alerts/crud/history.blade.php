@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@extends('layouts.app')
@section('template_title', 'Alert Notification History')
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
                            Alert Notification History
                            @if ($alertId)
                                <span class="badge bg-secondary ms-2">Alert #{{ $alertId }}</span>
                            @endif
                        </span>
                        <div class="d-flex gap-2">
                            @if ($alertId)
                                <a href="{{ route('myfinance2::price-alerts.history') }}"
                                   class="btn btn-outline-secondary btn-sm">
                                    Show All
                                </a>
                            @endif
                            <a href="{{ route('myfinance2::price-alerts.index') }}"
                               class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-fw fa-reply-all" aria-hidden="true"></i> Back to Alerts
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-3">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped data-table alert-history-table">
                                    <thead>
                                        <tr>
                                            <th>Sent At</th>
                                            <th>Alert #</th>
                                            <th>Symbol</th>
                                            <th>Type</th>
                                            <th class="text-right text-nowrap">Target Price</th>
                                            <th class="text-right text-nowrap">Price at Trigger</th>
                                            <th class="text-right text-nowrap">Projected Gain</th>
                                            <th>Channel</th>
                                            <th>Status</th>
                                            <th class="no-sort">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($items as $notif)
                                        @php
                                            $currencyCode = html_entity_decode(
                                                strip_tags($notif->priceAlertModel?->tradeCurrencyModel?->display_code ?? ''),
                                                ENT_QUOTES | ENT_HTML5, 'UTF-8'
                                            );
                                        @endphp
                                        <tr>
                                            <td class="text-nowrap">
                                                {{ $notif->sent_at ? $notif->sent_at->timezone(config('app.timezone'))->format('Y-m-d H:i') : '—' }}
                                            </td>
                                            <td>
                                                @if ($notif->priceAlertModel)
                                                    <a href="{{ route('myfinance2::price-alerts.edit',
                                                        $notif->price_alert_id) }}">
                                                        #{{ $notif->price_alert_id }}
                                                    </a>
                                                @else
                                                    #{{ $notif->price_alert_id }}
                                                @endif
                                            </td>
                                            <td>
                                                <a href="https://finance.yahoo.com/quote/{{ $notif->symbol }}"
                                                    target="_blank">
                                                    {{ $notif->symbol }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge
                                                    {{ $notif->alert_type === 'PRICE_ABOVE' ? 'bg-danger' : 'bg-primary' }}">
                                                    {{ $notif->alert_type === 'PRICE_ABOVE' ? '▲ Above' : '▼ Below' }}
                                                </span>
                                            </td>
                                            <td class="text-right text-nowrap">
                                                {{ MoneyFormat::get_formatted_price((float) $notif->target_price) }}
                                                {{ $currencyCode }}
                                            </td>
                                            <td class="text-right text-nowrap">
                                                {{ MoneyFormat::get_formatted_price((float) $notif->current_price) }}
                                                {{ $currencyCode }}
                                            </td>
                                            <td class="text-right text-nowrap"
                                                data-order="{{ $notif->projected_gain_eur ?? -999999 }}">
                                                @if ($notif->projected_gain_eur !== null)
                                                    @php $gainClass = $notif->projected_gain_eur >= 0 ? 'text-success' : 'text-danger'; @endphp
                                                    <span class="{{ $gainClass }}">
                                                        {{ $notif->projected_gain_eur >= 0 ? '+' : '' }}{{ number_format($notif->projected_gain_eur, 2) }} &euro;
                                                    </span>
                                                    <div class="{{ $gainClass }} small">
                                                        {{ $notif->projected_gain_pct >= 0 ? '+' : '' }}{{ number_format($notif->projected_gain_pct, 2) }}%
                                                        @if ($notif->projected_gain_eur < 0) ⚠️ @endif
                                                    </div>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $notif->notification_channel }}</td>
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
                                                      action="{{ route('myfinance2::price-alerts.history.destroy', $notif->id) }}"
                                                      onsubmit="return confirm('Delete this notification record? The alert will be able to re-trigger today.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="btn btn-sm btn-outline-danger w-100"
                                                            data-bs-toggle="tooltip"
                                                            title="Delete record — allows alert to re-trigger today">
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
<script type="module">
$(document).ready(function ()
{
    $('.alert-history-table.data-table').DataTable({
        'pageLength': 100,
        'order': [[ 0, 'desc' ]],
        'autoWidth': false,
        'columnDefs': [
            { targets: 'no-sort', sortable: false },
        ],
        'language': {
            'emptyTable': 'No notification history found.',
        },
    });
});
</script>
    @include('myfinance2::general.scripts.tooltips')
@endsection

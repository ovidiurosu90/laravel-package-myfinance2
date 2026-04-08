@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
<div id="bulk-action-bar" style="display:none" class="d-flex align-items-center gap-1 flex-wrap">
    <span id="bulk-selection-count" class="text-muted small fw-semibold me-1"></span>
    <form method="POST"
          action="{{ route('myfinance2::price-alerts.bulk-action') }}"
          id="bulk-action-form"
          class="d-flex gap-1 flex-wrap mb-0">
        @csrf
        <input type="hidden" name="action" id="bulk-action-input" value="">
        <button type="button"
                class="btn btn-sm btn-outline-warning"
                data-bulk-action="pause"
                data-bs-toggle="tooltip"
                title="Pause selected alerts"
                disabled>
            <i class="fa fa-fw fa-pause" aria-hidden="true"></i> Pause
        </button>
        <button type="button"
                class="btn btn-sm btn-outline-success"
                data-bulk-action="resume"
                data-bs-toggle="tooltip"
                title="Resume selected alerts"
                disabled>
            <i class="fa fa-fw fa-play" aria-hidden="true"></i> Resume
        </button>
        <button type="button"
                class="btn btn-sm btn-outline-danger"
                data-bulk-action="delete"
                data-bs-toggle="tooltip"
                title="Delete selected alerts (permanent)"
                disabled>
            <i class="fa fa-fw fa-trash" aria-hidden="true"></i> Delete
        </button>
        <button type="button"
                id="bulk-clear-selection"
                class="btn btn-sm btn-link text-secondary p-0 ms-1"
                data-bs-toggle="tooltip"
                title="Clear selection"
                disabled>
            <i class="fa fa-fw fa-times" aria-hidden="true"></i>
        </button>
    </form>
</div>

<div class="table-responsive">
    <table class="table table-sm table-striped data-table alert-items-table">
        <thead class="thead">
            <tr role="row">
                <th class="no-sort no-search" style="width: 1px;">
                    <input type="checkbox" id="select-all-alerts" title="Select all visible">
                </th>
                <th>Status</th>
                <th>Id</th>
                <th>Symbol</th>
                <th>Account(s)</th>
                <th>Type</th>
                <th class="text-right text-nowrap no-search">Current → Target Price</th>
                <th class="text-right text-nowrap">Projected Gain</th>
                <th class="text-center text-nowrap">Triggers</th>
                <th class="d-none d-xl-table-cell">Notes</th>
                <th class="d-none d-xl-table-cell">Created</th>
                <th class="no-search no-sort">Actions</th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
            </tr>
        </thead>
        <tbody class="table-body">
        @if ($items->count() > 0)
            @foreach ($items as $item)
            @php
                $gain = $projectedGains[$item->id] ?? null;
                $cp   = $currentPrices[$item->symbol] ?? null;
                $currencyCode = $item->tradeCurrencyModel ? $item->tradeCurrencyModel->display_code : '';
            @endphp
            <tr>
                <td>
                    <input type="checkbox"
                           class="alert-row-checkbox"
                           value="{{ $item->id }}">
                </td>
                <td>
                    <span class="badge {{ $item->getStatusBadgeClass() }}">
                        {{ $item->status }}
                    </span>
                </td>
                <td>{{ $item->id }}</td>
                <td>
                    <a href="https://finance.yahoo.com/quote/{{ $item->symbol }}"
                        target="_blank">
                        {{ $item->symbol }}
                    </a>
                </td>
                <td>
                    @if (!empty($accountNames[$item->symbol]))
                        @foreach ($accountNames[$item->symbol] as $accountName)
                            <div class="text-nowrap">{{ $accountName }}</div>
                        @endforeach
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td>
                    @php
                        $typeTitle = $item->alert_type === 'PRICE_ABOVE'
                            ? 'Triggers when the market price rises to or above the target'
                            : 'Triggers when the market price drops to or below the target';
                    @endphp
                    <span class="badge {{ $item->getAlertTypeBadgeClass() }}"
                          data-bs-toggle="tooltip"
                          data-bs-placement="top"
                          title="{{ $typeTitle }}">
                        {{ $item->alert_type === 'PRICE_ABOVE' ? '▲ Above' : '▼ Below' }}
                    </span>
                    <div>
                        <span class="badge bg-light text-dark border">{{ $item->source }}</span>
                    </div>
                </td>
                <td class="text-right text-nowrap"
                    data-order="{{ $cp !== null ? (float) $item->target_price - $cp : -999999 }}">
                    @if ($cp !== null)
                        @php
                            $delta     = (float) $item->target_price - $cp;
                            $deltaPct  = $cp > 0 ? ($delta / $cp) * 100 : 0;
                            $deltaSign = $delta >= 0 ? '+' : '−';
                        @endphp
                        <div class="text-nowrap">
                            {{ MoneyFormat::get_formatted_price($cp) }} {!! $currencyCode !!}
                            → {{ MoneyFormat::get_formatted_price((float) $item->target_price) }}
                            {!! $currencyCode !!}
                        </div>
                        <div class="text-muted small text-nowrap">
                            delta: {{ $deltaSign }}{{ MoneyFormat::get_formatted_price(abs($delta)) }}
                            {!! $currencyCode !!}
                            ({{ $deltaSign }}{{ number_format(abs($deltaPct), 2) }}%)
                        </div>
                    @else
                        <div class="text-nowrap">
                            <span class="text-muted">—</span>
                            → {{ MoneyFormat::get_formatted_price((float) $item->target_price) }}
                            {!! $currencyCode !!}
                        </div>
                    @endif
                </td>
                @php
                    $multiAccount = isset($accountNames[$item->symbol])
                        && count($accountNames[$item->symbol]) > 1;
                @endphp
                <td class="text-right text-nowrap"
                    data-order="{{ $gain ? $gain['gain_value'] : -999999 }}"
                    @if ($gain && $multiAccount)
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="Combined position across {{ count($accountNames[$item->symbol]) }} accounts: {{ implode(', ', $accountNames[$item->symbol]) }}"
                    @endif>
                    @if ($gain)
                        @php
                            $gainClass = $gain['gain_value'] >= 0 ? 'text-success' : 'text-danger';
                            $gainSign  = $gain['gain_value'] >= 0 ? '+' : '';
                            $isLoss    = $gain['gain_value'] < 0;
                            $fmtAvg    = MoneyFormat::get_formatted_price($gain['avg_cost']);
                            $fmtQty    = MoneyFormat::get_formatted_quantity_plain($gain['total_qty']);
                        @endphp
                        <span class="{{ $gainClass }} text-nowrap">
                            {{ $gainSign }}{{ number_format($gain['gain_value'], 2) }}
                            {!! $currencyCode !!}
                        </span>
                        <div class="{{ $gainClass }} small text-nowrap">
                            {{ $gainSign }}{{ number_format($gain['gain_pct'], 2) }}%
                            @if ($isLoss)
                                <span data-bs-toggle="tooltip"
                                      data-bs-placement="top"
                                      title="Target price is below your average cost — selling at target would still realize a loss">⚠️</span>
                            @endif
                        </div>
                        <div class="text-muted small text-nowrap">
                            {{ $fmtQty }}x @ avg {{ $fmtAvg }} {!! $currencyCode !!}
                        </div>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="text-center">
                    {{ $item->trigger_count }}
                    @if (!empty($recentNotifications[$item->id]))
                        @php $notifCount = count($recentNotifications[$item->id]); @endphp
                        <div class="mt-1">
                            <div class="text-muted text-nowrap"
                                 style="font-size:10px;font-weight:700;text-transform:uppercase">
                                {{ $notifCount === 1 ? 'Last triggered:' : "Last {$notifCount} triggers:" }}
                            </div>
                            @foreach ($recentNotifications[$item->id] as $notif)
                            <div class="text-muted small text-nowrap"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-custom-class="big-tooltips"
                                data-bs-html="true"
                                data-bs-title="Price: {{ MoneyFormat::get_formatted_price_plain($notif['current_price']) }}
                                    @if ($notif['projected_gain_eur'] !== null)
                                        <br>Gain: {{ number_format($notif['projected_gain_eur'], 2) }} EUR
                                        ({{ number_format($notif['projected_gain_pct'], 2) }}%)
                                    @endif">
                                {{ \Carbon\Carbon::parse($notif['sent_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                            </div>
                            @endforeach
                            <div class="mt-1">
                                <a href="{{ route('myfinance2::price-alerts.history', ['alert_id' => $item->id]) }}"
                                   class="small text-muted">view all →</a>
                            </div>
                        </div>
                    @elseif ($item->last_triggered_at)
                        <div class="mt-1">
                            <div class="text-muted text-nowrap"
                                 style="font-size:10px;font-weight:700;text-transform:uppercase">
                                Last triggered:
                            </div>
                            <div class="text-muted small text-nowrap">
                                {{ $item->last_triggered_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                            </div>
                        </div>
                    @endif
                </td>
                <td class="d-none d-xl-table-cell" style="white-space: normal;">
                    <span class="small text-muted">{{ $item->notes ?: '—' }}</span>
                </td>
                <td class="d-none d-xl-table-cell">{{ $item->created_at }}</td>
                <td class="text-nowrap">
                    @if ($item->status === 'ACTIVE')
                        @include('myfinance2::alerts.forms.pause-sm', ['id' => $item->id])
                    @else
                        @include('myfinance2::alerts.forms.resume-sm', ['id' => $item->id])
                    @endif
                    @php $orderAction = $item->alert_type === 'PRICE_ABOVE' ? 'SELL' : 'BUY'; @endphp
                    <a class="btn btn-sm btn-outline-success mt-1 w-100 d-block"
                        href="{{ route('myfinance2::orders.create', [
                            'symbol'   => $item->symbol,
                            'action'   => $orderAction,
                            'source'   => 'alert',
                            'alert_id' => $item->id,
                        ]) }}"
                        data-bs-toggle="tooltip"
                        title="Create {{ $orderAction }} order for {{ $item->symbol }}">
                        <i class="fa fa-fw fa-shopping-cart" aria-hidden="true"></i>
                        {{ $orderAction }}
                    </a>
                </td>
                <td>
                    <a class="btn btn-sm btn-outline-secondary w-100"
                        href="{{ route('myfinance2::price-alerts.edit', $item->id) }}"
                        data-bs-toggle="tooltip"
                        title="{{ trans('myfinance2::general.tooltips.edit-item',
                                        ['type' => 'Price Alert']) }}">
                        {!! trans('myfinance2::general.buttons.edit') !!}
                    </a>
                </td>
                <td>
                    @include('myfinance2::alerts.forms.delete-sm', ['id' => $item->id])
                </td>
            </tr>
            @endforeach
        @endif
        </tbody>
        <tfoot class="tfoot">
            <tr role="row">
                <th></th>
                <th>Status</th>
                <th>Id</th>
                <th>Symbol</th>
                <th>Account(s)</th>
                <th>Type</th>
                <th class="text-right text-nowrap no-search"></th>
                <th class="text-right text-nowrap">Projected Gain</th>
                <th class="text-center text-nowrap">Triggers</th>
                <th class="d-none d-xl-table-cell">Notes</th>
                <th class="d-none d-xl-table-cell">Created</th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
            </tr>
        </tfoot>
    </table>
    <div class="clearfix mb-3"></div>
</div>

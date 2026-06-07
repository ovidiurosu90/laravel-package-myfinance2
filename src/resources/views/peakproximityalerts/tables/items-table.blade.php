@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@if (empty($items))
    <p class="text-muted mb-0">
        @if ($view === 'active')
            No enabled symbols. Switch to <strong>All</strong> to enable the symbols you hold.
        @else
            No open positions to alert on.
        @endif
    </p>
@else
    <div class="table-responsive">
        <table class="table table-sm table-striped data-table peak-prox-table">
            <thead>
                <tr>
                    <th class="no-sort no-search" style="width: 1px;">
                        <input type="checkbox" id="select-all-peak" title="Select all visible">
                    </th>
                    <th>Symbol</th>
                    <th>Status</th>
                    <th>Alerts</th>
                    <th class="no-sort no-search">Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($items as $item)
                @php
                    $symbol    = $item['symbol'];
                    $enabled   = $item['enabled'];
                    $until     = $item['until'];
                    $last      = $item['last_alerted'];
                    $total     = $item['alert_total'];
                    $recent    = $item['recent_alerts'];
                    $throttled = $item['throttled_today'];
                @endphp
                <tr>
                    <td>
                        <input type="checkbox" class="peak-row-checkbox" value="{{ $symbol }}">
                    </td>
                    <td>
                        <a href="https://finance.yahoo.com/quote/{{ $symbol }}" target="_blank">
                            {{ $symbol }}
                        </a>
                    </td>
                    <td data-search="{{ $enabled ? 'ENABLED' : 'DISABLED' }}">
                        @if ($enabled)
                            <span class="badge bg-success">ENABLED</span>
                        @else
                            <span class="badge bg-secondary">DISABLED</span>
                        @endif
                        @if ($until)
                            <div class="text-muted small">
                                until {{ $until->format('Y-m-d') }}
                                ({{ $enabled ? 'then disabled' : 'then enabled' }})
                            </div>
                        @endif
                    </td>
                    <td class="text-nowrap"
                        data-order="{{ $last && $last->sent_at ? $last->sent_at->timestamp : 0 }}">
                        @if ($total > 0)
                            <span class="fw-semibold">{{ $total }}</span><span class="text-muted small"> triggered</span>@if ($recent->isNotEmpty()), <span class="text-muted" style="font-size:10px">last:</span>@endif
                            <div class="mt-1">
                                @foreach ($recent as $notif)
                                    <div class="text-muted small text-nowrap"
                                         data-bs-toggle="tooltip"
                                         data-bs-placement="top"
                                         data-bs-custom-class="big-tooltips"
                                         data-bs-html="true"
                                         data-bs-title="Windows: {{ $notif->triggered_windows }}
                                            @if ($notif->closest_proximity_pct !== null)
                                                <br>Closest: {{ MoneyFormat::get_formatted_pct((float) $notif->closest_proximity_pct) }}%
                                            @endif">
                                        {{ $notif->sent_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                                    </div>
                                @endforeach
                                <div class="mt-1">
                                    <a href="{{ route('myfinance2::peak-proximity-alerts.history', ['symbol' => $symbol]) }}"
                                       class="small text-muted">view history &rarr;</a>
                                </div>
                                @if ($throttled)
                                    <form method="POST" class="mb-0 mt-1"
                                          action="{{ route('myfinance2::peak-proximity-alerts.rearm') }}">
                                        @csrf
                                        <input type="hidden" name="view" value="{{ $view }}">
                                        <input type="hidden" name="symbols[]" value="{{ $symbol }}">
                                        <button type="submit" class="btn btn-sm btn-outline-warning"
                                                data-bs-toggle="tooltip"
                                                title="Clear today's alert so the next run can email {{ $symbol }} again">
                                            <i class="fa fa-fw fa-refresh" aria-hidden="true"></i> Re-arm
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @else
                            <span class="text-muted small">never triggered</span>
                        @endif
                    </td>
                    <td>
                        <form method="POST" class="mb-0"
                              action="{{ $enabled
                                  ? route('myfinance2::peak-proximity-alerts.disable')
                                  : route('myfinance2::peak-proximity-alerts.enable') }}">
                            @csrf
                            <input type="hidden" name="view" value="{{ $view }}">
                            <input type="hidden" name="symbols[]" value="{{ $symbol }}">
                            @if ($enabled)
                                <button type="submit" class="btn btn-sm btn-outline-secondary w-100"
                                        data-bs-toggle="tooltip"
                                        title="Stop daily near-peak emails for {{ $symbol }}">
                                    <i class="fa fa-fw fa-bell-slash" aria-hidden="true"></i> Disable
                                </button>
                            @else
                                <button type="submit" class="btn btn-sm btn-outline-success w-100"
                                        data-bs-toggle="tooltip"
                                        title="Send a daily near-peak email for {{ $symbol }}">
                                    <i class="fa fa-fw fa-bell" aria-hidden="true"></i> Enable
                                </button>
                            @endif
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

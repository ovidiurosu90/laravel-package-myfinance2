{{-- Returns Alerts --}}
@if(!empty($alerts))
<div class="card mb-4 border-warning">
    <div class="card-header bg-warning text-dark" style="cursor: pointer;"
        data-bs-toggle="collapse"
        data-bs-target="#returns-alerts-content">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span>
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                {{ trans('myfinance2::returns.alerts.title') }}
                <small class="ms-2">({{ count($alerts) }} {{ count($alerts) === 1 ? 'alert' : 'alerts' }})</small>
            </span>
            <i class="fa-solid fa-chevron-down"></i>
        </div>
    </div>
    <div class="card-body collapse show" id="returns-alerts-content">
        @foreach($alerts as $alert)
            <div class="alert alert-warning mb-3">
                <h6 class="alert-heading">
                    <i class="fa-solid fa-exclamation-circle me-1"></i>
                    {{ $alert['message'] }}
                </h6>
                <hr class="my-2">

                @if($alert['type'] === 'split_adjustment_pairs')
                    {{-- Split adjustment pairs table --}}
                    <table class="table table-sm table-bordered mb-0" style="background-color: white;">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Symbol</th>
                                <th class="text-end">Sell ID</th>
                                <th class="text-end">Sell Qty</th>
                                <th class="text-end">Buy ID</th>
                                <th class="text-end">Buy Qty</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($alert['trades'] as $trade)
                                <tr>
                                    <td>{{ $trade['date'] }}</td>
                                    <td><strong>{{ $trade['symbol'] }}</strong></td>
                                    <td class="text-end">
                                        @if($trade['sell_excluded'])
                                            <span class="text-muted">{{ $trade['sell_id'] }}</span>
                                        @else
                                            <strong class="text-danger">{{ $trade['sell_id'] }}</strong>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($trade['sell_quantity']) }}</td>
                                    <td class="text-end">
                                        @if($trade['buy_excluded'])
                                            <span class="text-muted">{{ $trade['buy_id'] }}</span>
                                        @else
                                            <strong class="text-danger">{{ $trade['buy_id'] }}</strong>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($trade['buy_quantity']) }}</td>
                                    <td>
                                        @if($trade['sell_excluded'] && $trade['buy_excluded'])
                                            <span class="badge bg-success">Excluded</span>
                                        @elseif($trade['sell_excluded'] || $trade['buy_excluded'])
                                            <span class="badge bg-warning text-dark">Partial</span>
                                        @else
                                            <span class="badge bg-danger">Needs exclusion</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @elseif(str_starts_with($alert['type'], 'missing_exchange_rate'))
                    {{-- Exchange rate overrides table --}}
                    <table class="table table-sm table-bordered mb-0" style="background-color: white;">
                        <thead class="table-light">
                            <tr>
                                <th>Pair</th>
                                <th>Dates Checked</th>
                                <th>Accounts Missing Override</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($alert['pairs'] as $pair)
                                <tr>
                                    <td><strong>{{ $pair['pair'] }}</strong></td>
                                    <td>{{ $pair['dates_checked'] }}</td>
                                    <td>
                                        @foreach($pair['accounts_missing'] as $account)
                                            <span class="badge bg-danger">{{ $account['name'] }}</span>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @elseif(str_starts_with($alert['type'], 'missing_position_date_override'))
                    {{-- Position date overrides table (vested/moved positions) --}}
                    <table class="table table-sm table-bordered mb-0" style="background-color: white;">
                        <thead class="table-light">
                            <tr>
                                <th>Symbol</th>
                                <th class="text-end">Quantity</th>
                                <th>Account</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($alert['positions'] as $position)
                                <tr>
                                    <td><strong>{{ $position['symbol'] }}</strong></td>
                                    <td class="text-end">{{ $position['quantityFormatted'] }}</td>
                                    <td>{{ $position['account_name'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @elseif($alert['type'] === 'missing_cash_override_for_position')
                    {{-- Missing deposits/withdrawals overrides for position date overrides --}}
                    <table class="table table-sm table-bordered mb-0" style="background-color: white;">
                        <thead class="table-light">
                            <tr>
                                <th>Position Override Date</th>
                                <th>Account</th>
                                <th>Missing Override Year</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($alert['items'] as $item)
                                <tr>
                                    <td><strong>{{ $item['date'] }}</strong></td>
                                    <td>{{ $item['account_name'] }}</td>
                                    <td>
                                        <span class="badge bg-danger">{{ $item['year_needed'] }}</span>
                                        <small class="text-muted">
                                            (needs deposits_overrides or withdrawals_overrides)
                                        </small>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    {{-- Price override positions table --}}
                    <table class="table table-sm table-bordered mb-0" style="background-color: white;">
                        <thead class="table-light">
                            <tr>
                                <th>Symbol</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">API Price</th>
                                <th class="text-end">Recent Trade Price</th>
                                <th class="text-end">Ratio</th>
                                <th>Account</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($alert['positions'] as $position)
                                <tr>
                                    <td><strong>{{ $position['symbol'] }}</strong></td>
                                    <td class="text-end">{{ $position['quantityFormatted'] }}</td>
                                    <td class="text-end">{{ $position['apiPrice'] ?? '-' }}</td>
                                    <td class="text-end">{{ $position['recentTradePrice'] ?? '-' }}</td>
                                    <td class="text-end">
                                        @if(!empty($position['detectedRatio']))
                                            <span class="badge bg-danger">{{ $position['detectedRatio'] }}x</span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $position['account_name'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endforeach
        <p class="text-muted small mb-0">
            <i class="fa-solid fa-info-circle me-1"></i>
            {{ trans('myfinance2::returns.alerts.hint') }}
        </p>
    </div>
</div>
@endif


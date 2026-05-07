<div class="card-title">
    Account: {{ $openPosition['accountModel']->name }}
    ({!! $openPosition['accountModel']->currency->display_code !!})
</div>
<div class="card-text">
    <div class="d-flex flex-wrap gap-4 align-items-start">
        <div>
            <div class="fw-semibold text-muted small mb-2">Overview</div>
            <table class="metrics table table-borderless table-sm">
                <tbody>
                <tr>
                    <td>Quantity</td>
                    <td>{{ $openPosition['quantity'] }}</td>
                    <td></td>
                </tr>
                <tr>
                    <td>MValue</td>
                    <td>{!! $openPosition['market_value_in_account_currency_formatted'] !!}</td>
                    <td></td>
                </tr>
                <tr>
                    <td>
                        @if($openPosition['cost2_in_account_currency_formatted'])
                            <span data-bs-toggle="tooltip"
                                  title="Actual purchase cost of your currently held shares, based on the weighted average of all purchase prices.">Cost Basis</span>
                        @else
                            Cost
                        @endif
                    </td>
                    <td>
                        @if($openPosition['cost2_in_account_currency_formatted'])
                            {!! $openPosition['cost2_in_account_currency_formatted'] !!}
                        @else
                            @if(($openPosition['cost_in_account_currency'] ?? 0) < 0)
                            <i class="fas fa-info-circle text-muted me-1"
                               data-bs-toggle="tooltip"
                               title="Total cost is negative because your sell proceeds exceeded your total buy cost. Your remaining shares are effectively free."></i>
                            @endif
                            {!! $openPosition['cost_in_account_currency_formatted'] !!}
                        @endif
                    </td>
                    <td></td>
                </tr>
                @if($openPosition['cost2_in_account_currency_formatted'])
                <tr class="fst-italic" style="opacity: 0.55">
                    <td class="ps-3">
                        <span data-bs-toggle="tooltip"
                              title="Net cash still deployed: total amount invested minus proceeds already collected from sales.">Effective Cost</span>
                    </td>
                    <td>
                        @if(($openPosition['cost_in_account_currency'] ?? 0) < 0)
                        <i class="fas fa-info-circle text-muted me-1"
                           data-bs-toggle="tooltip"
                           title="Negative because sell proceeds exceeded total buy cost. Remaining shares are effectively free."></i>
                        @endif
                        {!! $openPosition['cost_in_account_currency_formatted'] !!}
                    </td>
                    <td></td>
                </tr>
                @endif
                <tr>
                    <td>
                        <span data-bs-toggle="tooltip"
                              title="Average price paid per share across all purchases.">Avg Cost</span>
                    </td>
                    <td>
                        @if($openPosition['average_unit_cost2_in_trade_currency_formatted'])
                            {!! $openPosition['average_unit_cost2_in_trade_currency_formatted'] !!}
                        @else
                            @if(($openPosition['average_unit_cost_in_trade_currency'] ?? 0) < 0)
                            <i class="fas fa-info-circle text-muted me-1"
                               data-bs-toggle="tooltip"
                               title="Negative because sell proceeds exceeded total buy cost. Remaining shares are effectively free."></i>
                            @endif
                            {!! $openPosition['average_unit_cost_in_trade_currency_formatted'] !!}
                        @endif
                    </td>
                    <td></td>
                </tr>
                @if($openPosition['average_unit_cost2_in_trade_currency_formatted'])
                <tr class="fst-italic" style="opacity: 0.55">
                    <td class="ps-3">
                        <span data-bs-toggle="tooltip"
                              title="Per-share equivalent of net cash deployed. Below Avg Cost when past sales were profitable.">Effective Avg Cost</span>
                    </td>
                    <td>
                        @if(($openPosition['average_unit_cost_in_trade_currency'] ?? 0) < 0)
                        <i class="fas fa-info-circle text-muted me-1"
                           data-bs-toggle="tooltip"
                           title="Negative because sell proceeds exceeded total buy cost. Remaining shares are effectively free."></i>
                        @endif
                        {!! $openPosition['average_unit_cost_in_trade_currency_formatted'] !!}
                    </td>
                    <td></td>
                </tr>
                @endif
                <tr>
                    <td>
                        @if($openPosition['overall_change2_in_account_currency_formatted'])
                            <span data-bs-toggle="tooltip"
                                  title="Paper gain/loss on your currently held shares vs. their actual average purchase price.">Unrealized Gain</span>
                        @else
                            Gain
                        @endif
                    </td>
                    <td>
                        @if($openPosition['overall_change2_in_account_currency_formatted'])
                            {!! $openPosition['overall_change2_in_account_currency_formatted'] !!}
                        @else
                            {!! $openPosition['overall_change_in_account_currency_formatted'] !!}
                        @endif
                    </td>
                    <td>
                        @if($openPosition['overall_change2_in_account_currency_formatted'])
                            {!! $openPosition['overall_change2_in_percentage_formatted'] !!}
                        @else
                            @if($openPosition['overall_change_in_percentage'] === null && $openPosition['quantity'])
                            <i class="fas fa-info-circle text-muted me-1"
                               data-bs-toggle="tooltip"
                               title="Not applicable. Total cost is negative (sell proceeds exceeded buy cost), so a percentage cannot be meaningfully calculated."></i>
                            @endif
                            {!! $openPosition['overall_change_in_percentage_formatted'] !!}
                        @endif
                    </td>
                </tr>
                @if($openPosition['overall_change2_in_account_currency_formatted'])
                <tr class="fst-italic" style="opacity: 0.55">
                    <td class="ps-3">
                        <span data-bs-toggle="tooltip"
                              title="Profit locked in from shares already sold, calculated using the average cost method.">Realized Gain</span>
                    </td>
                    <td>{!! $openPosition['realized_gain_in_account_currency_formatted'] !!}</td>
                    <td>{!! $openPosition['realized_gain_in_percentage_formatted'] !!}</td>
                </tr>
                <tr class="fst-italic" style="opacity: 0.55">
                    <td class="ps-3">
                        <span data-bs-toggle="tooltip"
                              title="Total gain: unrealized gain on held shares plus realized gain from past sales.">Total Gain</span>
                    </td>
                    <td>{!! $openPosition['overall_change_in_account_currency_formatted'] !!}</td>
                    <td>
                        @if($openPosition['overall_change_in_percentage'] === null && $openPosition['quantity'])
                        <i class="fas fa-info-circle text-muted me-1"
                           data-bs-toggle="tooltip"
                           title="Not applicable. Total cost is negative (sell proceeds exceeded buy cost), so a percentage cannot be meaningfully calculated."></i>
                        @endif
                        {!! $openPosition['overall_change_in_percentage_formatted'] !!}
                    </td>
                </tr>
                @endif
                </tbody>
            </table>
        </div>

        <div>
            <div class="fw-semibold text-muted small mb-2">Trades</div>
            @php
                $symbolSplits = $quoteData['stock_splits'] ?? [];
                $timeline = [];
                foreach ($openPosition['trades'] as $trade) {
                    $timeline[] = [
                        'type' => 'trade',
                        'date' => $trade->timestamp->format('Y-m-d'),
                        'data' => $trade,
                    ];
                }
                foreach ($symbolSplits as $split) {
                    $timeline[] = [
                        'type' => 'split',
                        'date' => $split->split_date->format('Y-m-d'),
                        'data' => $split,
                    ];
                }
                usort($timeline, fn ($a, $b) => $b['date'] <=> $a['date']);
            @endphp
            <table class="trades table table-borderless table-sm">
            @foreach ($timeline as $event)
                @if ($event['type'] === 'split')
                    @php $splitItem = $event['data']; @endphp
                    <tr class="text-muted">
                        <td>{{ $event['date'] }}</td>
                        <td colspan="3">
                            <span class="badge bg-warning text-dark"
                                  data-bs-toggle="tooltip"
                                  data-bs-placement="top"
                                  title="Split applied: qty × {{ $splitItem->ratio_numerator }}, price ÷ {{ $splitItem->ratio_numerator }}">
                                &#9988; {{ $splitItem->getRatioLabel() }} split
                            </span>
                        </td>
                    </tr>
                @else
                    @php $trade = $event['data']; @endphp
                    <tr>
                        <td>{{ $event['date'] }}</td>
                        <td>{{ strtolower($trade->action) }}</td>
                        <td>
                            {{ is_int($trade->quantity)
                                ? (int)$trade->quantity
                                : round($trade->quantity, 4) }}x
                        </td>
                        <td class="text-end">
                            {{ round($trade->unit_price, 2) }}
                            {!! $trade->tradeCurrencyModel->display_code !!}
                        </td>
                    </tr>
                @endif
            @endforeach
            </table>
        </div>
    </div>
</div>

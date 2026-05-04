<div class="card-title">Account:
    {{ $openPosition['accountModel']->name }}
    ({!! $openPosition['accountModel']
            ->currency->display_code !!})
</div>
<div class="card-text">
    Quantity: {{ $openPosition['quantity'] }}
    <br />
    Total Cost: {!! $openPosition['cost_in_account_currency_formatted'] !!}
    @if(($openPosition['cost_in_account_currency'] ?? 0) < 0)
    <i class="fas fa-info-circle text-muted ms-1"
       data-bs-toggle="tooltip"
       title="Total cost is negative because your sell proceeds exceeded your total buy cost for this position. Your remaining shares are effectively free, as you have already recouped more than your full investment."></i>
    @endif
    <br />
    Value: {!! $openPosition['market_value_in_account_currency_formatted'] !!}
    <br />
    Change value: {!! $openPosition['overall_change_in_account_currency_formatted']
                   !!}
    <br />
    Change %: {!! $openPosition['overall_change_in_percentage_formatted'] !!}
    @if($openPosition['overall_change_in_percentage'] === null && $openPosition['quantity'])
    <i class="fas fa-info-circle text-muted ms-1"
       data-bs-toggle="tooltip"
       title="Change percentage is not applicable because the total cost of this position is negative. A percentage return cannot be meaningfully calculated without a positive reference cost."></i>
    @endif
    <br />
    Average Cost: {!! $openPosition['average_unit_cost_in_trade_currency_formatted']
                   !!}
    @if(($openPosition['average_unit_cost_in_trade_currency'] ?? 0) < 0)
    <i class="fas fa-info-circle text-muted ms-1"
       data-bs-toggle="tooltip"
       title="Average cost is negative because your sell proceeds exceeded your total buy cost for this position. Your remaining shares are effectively free, as you have already recouped more than your full investment."></i>
    @endif
    @if($openPosition['average_unit_cost2_in_trade_currency_formatted'])
    <br />
    Average Cost 2: {!! $openPosition['average_unit_cost2_in_trade_currency_formatted'] !!}
    <i class="fas fa-info-circle text-muted ms-1"
       data-bs-toggle="tooltip"
       title="Value without factoring any gains from selling actions!"></i>
    @endif
    <hr />

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
    <table class="trades table-borderless">
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


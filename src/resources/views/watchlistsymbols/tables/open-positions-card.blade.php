<div class="card-title">Account:
    {{ $openPosition['accountModel']->name }}
    ({!! $openPosition['accountModel']
            ->currency->display_code !!})
</div>
<div class="card-text">
    Quantity: {{ $openPosition['quantity'] }}
    <br />
    Total Cost: {!! $openPosition['cost_in_account_currency_formatted'] !!}
    <br />
    Value: {!! $openPosition['market_value_in_account_currency_formatted'] !!}
    <br />
    Change value: {!! $openPosition['overall_change_in_account_currency_formatted']
                   !!}
    <br />
    Change %: {!! $openPosition['overall_change_in_percentage_formatted'] !!}
    <br />
    Average Cost: {!! $openPosition['average_unit_cost_in_trade_currency_formatted']
                   !!}
    @if($openPosition['average_unit_cost2_in_trade_currency_formatted'])
    <br />
    Average Cost 2:
    <span data-bs-toggle="tooltip"
        title="Value without factoring any gains from
            selling actions!"
        style="font-style:italic">
        {!! $openPosition['average_unit_cost2_in_trade_currency_formatted'] !!}
    </span>
    @endif
    <hr />

    <table class="trades table-borderless">
    @foreach($openPosition['trades'] as $keyTrade => $trade)
        <tr>
            <td>{{ $trade->timestamp->format('Y-m-d') }}</td>
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
    @endforeach
    </table>
</div>


{{-- The Open positions card of the peak-proximity alert: each account's position and its trade / split timeline.
     Included by peak-proximity-alert, whose variables it reads. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@if (!empty($openPositions))
<div class="section" style="border-left-color:#28a745;">
    <h2>Open positions</h2>
    @foreach ($openPositions as $openPosition)
    <div class="acct-block">
        <div class="acct-title">
            Account: {{ $openPosition['accountModel']->name }}
            ({!! $decode($openPosition['accountModel']->currency->display_code) !!})
        </div>
        <table>
            <tr>
                <th>Quantity</th>
                <th>MValue</th>
                <th>Cost Basis</th>
                <th>Avg Cost</th>
                <th>Gain</th>
            </tr>
            <tr>
                <td class="text-nowrap">{{ MoneyFormat::get_formatted_quantity_plain($openPosition['quantity']) }}</td>
                <td>{!! $decode($openPosition['market_value_in_account_currency_formatted']) !!}</td>
                <td>
                    {!! $decode($openPosition['cost2_in_account_currency_formatted']
                        ?: $openPosition['cost_in_account_currency_formatted']) !!}
                </td>
                <td>
                    {!! $decode($openPosition['average_unit_cost2_in_trade_currency_formatted']
                        ?: $openPosition['average_unit_cost_in_trade_currency_formatted']) !!}
                </td>
                <td>
                    {!! $decode($openPosition['overall_change2_in_account_currency_formatted']
                        ?: $openPosition['overall_change_in_account_currency_formatted']) !!}
                </td>
            </tr>
        </table>

        <table>
            <tr>
                <th>Date</th>
                <th>Action</th>
                <th>Quantity</th>
                <th>Unit price</th>
            </tr>
            @foreach ($openPosition['timeline'] as $event)
            @if ($event['type'] === 'split')
            @php $splitItem = $event['data']; @endphp
            <tr class="text-muted">
                <td class="text-nowrap">{{ $splitItem->split_date->format('Y-m-d') }}</td>
                <td colspan="3">&#9988; {{ $splitItem->getRatioLabel() }} split</td>
            </tr>
            @else
            @php $trade = $event['data']; @endphp
            <tr>
                <td class="text-nowrap">{{ $trade->timestamp->format('Y-m-d') }}</td>
                <td>{{ strtolower($trade->action) }}</td>
                <td class="text-nowrap">{{ MoneyFormat::get_formatted_quantity_plain($trade->quantity) }}x</td>
                <td class="text-nowrap">
                    {!! $decode(MoneyFormat::get_formatted_price_display(
                        $trade->tradeCurrencyModel->display_code,
                        (float) $trade->unit_price,
                        true
                    )) !!}
                </td>
            </tr>
            @endif
            @endforeach
        </table>
    </div>
    @endforeach
</div>
@endif

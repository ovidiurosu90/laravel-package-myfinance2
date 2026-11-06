{{-- The Open positions card of the peak-proximity alert: each account's position and its trade / split timeline
     (partials/position-trades, shared with the price alert). Included by peak-proximity-alert, whose variables it
     reads. --}}
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

        @include('myfinance2::emails.partials.position-trades', ['timeline' => $openPosition['timeline']])
    </div>
    @endforeach
</div>
@endif

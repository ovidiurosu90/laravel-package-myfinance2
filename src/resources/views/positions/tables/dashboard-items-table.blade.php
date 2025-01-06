<div class="table-responsive">
    <table class="table table-sm table-striped data-table
                  positions-dashboard-items-table">
        <thead class="thead">
            <tr role="row">
                <th>Symbol</th>
                <th data-bs-toggle="tooltip" title="Trade Currency">Currency</th>
                <th style="min-width: 168px">Market</th>
                <th>Quantity</th>
                <th class="text-right" data-bs-toggle="tooltip"
                    title="Cost in account currency"
                    style="min-width: 95px">Cost</th>
                <th class="text-right" data-bs-toggle="tooltip"
                    title="Current market value in account currency"
                    style="min-width: 135px">Mkt value*</th>
                <th class="text-right" data-bs-toggle="tooltip"
                    title="Average purchased unit cost in trade currency"
                    style="min-width: 86px">Avg cost</th>
                <th class="text-right" data-bs-toggle="tooltip"
                    title="Current unit price in trade currency"
                    style="min-width: 92px">Price*</th>
                <th class="text-right" data-bs-toggle="tooltip"
                    title="Day gain in account currency"
                    style="min-width: 88px">Day gain*</th>
                <th class="text-right" data-bs-toggle="tooltip"
                    title="Day gain in percentage"
                    style="min-width: 106px">Day gain (%)*</th>
                <th class="text-right" data-bs-toggle="tooltip"
                    title="Overall gain in account currency"
                    style="min-width: 90px">Gain*</th>
                <th class="text-right" data-bs-toggle="tooltip"
                    title="Overall gain in percentage"
                    style="min-width: 80px">Gain (%)*</th>
                <th>Symbol</th>
            </tr>
        </thead>
        <tbody class="table-body">
        @if( count($items) > 0)
            @foreach($items as $item)
            <tr>
                <td data-bs-toggle="tooltip"
                    title="{!! $item['symbol_name'] !!}">
                    <a href="https://finance.yahoo.com/quote/{{$item['symbol'] }}"
                        target="_blank">
                        {{ $item['symbol'] }}
                    </a>
                </td>
                <td>{!! $item['tradeCurrencyModel']->display_code !!}</td>
                <td>
                    <div class="row m-0">
                        {!! $item['marketUtils']->getMarketStatusFormatted() !!}
                    </div>
                </td>
                <td>
                    <div class="row m-0">
                        <div class="col pr-2 pl-2 ml-2 mr-2"
                            style="line-height:1.5rem">
                            {{ $item['quantity'] }}
                        </div>
                        @if($item['quantity'] == 0)
                        <div class="col pr-2 pl-2">
                            @include('myfinance2::trades.forms.close-symbol-sm', [
                                'accountModel' =>
                                    $accountData[$accountId]['accountModel'],
                                'symbol'       => $item['symbol'],
                            ])
                        </div>
                        @endif
                    </div>
                </td>
                <td class="text-right">
                    {!! $item['cost_in_account_currency'] !!}
                    @if($item['cost2_in_account_currency'])
                    <br />
                    <span data-bs-toggle="tooltip"
                        title="Value without factoring any gains from
                                selling actions!"
                        style="font-style:italic">
                        {!! $item['cost2_in_account_currency'] !!}
                    </span>
                    @endif
                </td>
                <td class="text-right" data-bs-toggle="tooltip"
                    data-bs-custom-class="big-tooltips"
                    title="Quote timestamp: {{ $item['quote_timestamp'] }}">
                    {!! $item['market_value_in_account_currency'] !!}
                </td>
                <td class="text-right pr-2">
                    {!! $item['average_unit_cost_in_trade_currency'] !!}
                    @if($item['average_unit_cost2_in_trade_currency'])
                    <br />
                    <span data-bs-toggle="tooltip"
                        title="Value without factoring any gains from
                            selling actions!"
                        style="font-style:italic">
                        {!! $item['average_unit_cost2_in_trade_currency'] !!}
                    </span>
                    @endif
                </td>
                <td class="text-right pr-2" data-bs-toggle="tooltip"
                    data-bs-custom-class="big-tooltips"
                    title="Quote timestamp: {{ $item['quote_timestamp'] }}">
                    {!! $item['current_unit_price_in_trade_currency'] !!}
                </td>
                <td class="text-right pr-2">
                    {!! $item['day_change_in_account_currency'] !!}
                </td>
                <td class="text-right pr-2">
                    {!! $item['day_change_in_percentage'] !!}
                </td>
                <td class="text-right pr-2">
                    {!! $item['overall_change_in_account_currency'] !!}
                    @if($item['overall_change2_in_account_currency'])
                    <br />
                    <span data-bs-toggle="tooltip"
                        title="Value without factoring any gains from
                                selling actions!"
                        style="font-style:italic">
                        {!! $item['overall_change2_in_account_currency'] !!}
                    </span>
                    @endif
                </td>
                <td class="text-right pr-2">
                    {!! $item['overall_change_in_percentage'] !!}
                    @if($item['overall_change2_in_percentage'])
                    <br />
                    <span data-bs-toggle="tooltip"
                        title="Value without factoring any gains from
                                selling actions!"
                        style="font-style:italic">
                        {!! $item['overall_change2_in_percentage'] !!}
                    </span>
                    @endif
                </td>
                <td data-bs-toggle="tooltip"
                    title="{!! $item['symbol_name'] !!}">
                    <a href="https://finance.yahoo.com/quote/{{ $item['symbol'] }}"
                        target="_blank">{{ $item['symbol'] }}</a>
                </td>
            </tr>
            @endforeach
        @endif
        </tbody>
        <tfoot class="tfoot">
            <tr>
                <td colspan="3"></td>
                <td colspan="2" class="text-right">
                    <span class="font-weight-bold" data-bs-toggle="tooltip"
                        title="Total Cost in account currency">
                        Total:
                    </span>
                    {!! $accountData[$accountId]['total_cost_formatted'] !!}
                </td>
                <td class="text-right">
                    <span class="font-weight-bold" data-bs-toggle="tooltip"
                        title="Total Current Market Value in account currency">
                        Total:
                    </span>
                    {!! $accountData[$accountId]['total_market_value_formatted'] !!}
                </td>
                <td colspan="3"></td>
                <td colspan="2" class="text-right pr-2">
                    <span class="font-weight-bold" data-bs-toggle="tooltip"
                        title="Total Overall Gain in account currency">
                        Total:
                    </span>
                    {!! $accountData[$accountId]['total_change_formatted'] !!}
                </td>
                <td colspan="2"></td>
            </tr>
            <tr>
                <td colspan="5"></td>
                <td class="text-right">
                    <span class="font-weight-bold" data-bs-toggle="tooltip"
                        title="Total Cash & Cash Alternatives in Account Currency">
                        Cash:
                    </span>
                    {!! $accountData[$accountId]['cash']->getFormattedAmount() !!}
                </td>
                <td colspan="7" class="text-left">
                    {!! $accountData[$accountId]['cash']->getFormattedDetails() !!}
                </td>
            </tr>
        </tfoot>
    </table>
    <div class="clearfix mb-3"></div>
</div>


<div class="table-responsive">
    <table class="table table-sm table-striped data-table
                  positions-dashboard-items-table">
        <thead class="thead">
            <tr role="row">
                <th class="text-nowrap">Symbol</th>
                <th class="text-nowrap no-search no-sort" data-bs-toggle="tooltip"
                    title="Quote Price in Trade Currency">Quote Price</th>
                <th class="text-nowrap no-sort">Market</th>
                <th class="text-right text-nowrap" data-bs-toggle="tooltip"
                    title="Cost in account currency">Cost</th>
                <th class="text-right text-nowrap" data-bs-toggle="tooltip"
                    title="Current market value in account currency">MValue*</th>
                <th class="no-sort text-right text-nowrap"
                    data-bs-toggle="tooltip"
                    title="Average purchased unit cost in trade currency">Avg cost</th>
                <th class="no-sort text-right text-nowrap"
                    data-bs-toggle="tooltip"
                    title="Current unit price in trade currency">Price*</th>
                <th class="text-right text-nowrap" data-bs-toggle="tooltip"
                    title="Day gain in account currency">Day gain*</th>
                <th class="text-right text-nowrap" data-bs-toggle="tooltip"
                    title="Day gain in percentage">Day gain (%)*</th>
                <th class="text-right text-nowrap" data-bs-toggle="tooltip"
                    title="Overall gain in account currency">Gain*</th>
                <th class="text-right text-nowrap" data-bs-toggle="tooltip"
                    title="Overall gain in percentage">Gain (%)*</th>
            </tr>
        </thead>
        <tbody class="table-body">
        @if(count($items) > 0)
            @foreach($items as $item)
            <tr>
                <td class="text-nowrap" data-bs-toggle="tooltip"
                    title="{!! $item['symbol_name'] !!}">
                    @if (!\ovidiuro\myfinance2\App\Services\FinanceAPI::isUnlisted(
                        $item['symbol']))
                    <a href="https://finance.yahoo.com/quote/{{ $item['symbol'] }}"
                        target="_blank">
                        {{ $item['symbol'] }}
                    </a>
                    @else
                    <span class="unlisted small">{{ $item['symbol'] }}</span>
                    @endif
                    <div>
                        x {{ $item['quantity'] }}
                    </div>
                    @if($item['quantity'] == 0)
                    <div>
                        @include('myfinance2::trades.forms.close-symbol-sm', [
                            'accountModel' =>
                                $accountData[$accountId]['accountModel'],
                            'symbol'       => $item['symbol'],
                        ])
                    </div>
                    @endif
                </td>
                <td class="chart-symbol text-nowrap"
                    data-account_id="{{ $accountId }}"
                    data-symbol="{{ $item['symbol'] }}"
                    data-symbol_name="{{ $item['symbol_name'] }}"
                    data-base_value="{{
                        $item['average_unit_cost_in_trade_currency']
                    }}"
                    data-trade_currency_formatted="{!!
                        $item['tradeCurrencyModel']->display_code
                    !!}"
                    style="position: relative"></td>
                <td class="text-nowrap">
                    <div class="row m-0">
                        {!! !empty($item['marketUtils'])
                            ? $item['marketUtils']->getMarketStatusFormatted()
                            : '' !!}
                    </div>
                </td>
                <td class="text-right text-nowrap">
                    {!! $item['cost_in_account_currency_formatted'] !!}
                    @if($item['cost2_in_account_currency_formatted'])
                    <br />
                    <span data-bs-toggle="tooltip"
                        title="Value without factoring any gains from
                                selling actions!"
                        style="font-style:italic">
                        {!! $item['cost2_in_account_currency_formatted'] !!}
                    </span>
                    @endif
                </td>
                <td class="text-right text-nowrap" data-bs-toggle="tooltip"
                    data-bs-custom-class="big-tooltips"
                    title="Quote timestamp: {{ $item['quote_timestamp_formatted']
                                            }}">
                    {!! $item['market_value_in_account_currency_formatted'] !!}
                </td>
                <td class="text-right text-nowrap">
                    {!! $item['average_unit_cost_in_trade_currency_formatted'] !!}
                    @if($item['average_unit_cost2_in_trade_currency_formatted'])
                    <br />
                    <span data-bs-toggle="tooltip"
                        title="Value without factoring any gains from
                            selling actions!"
                        style="font-style:italic">
                        {!! $item['average_unit_cost2_in_trade_currency_formatted']
                         !!}
                    </span>
                    @endif
                </td>
                <td class="text-right text-nowrap" data-bs-toggle="tooltip"
                    data-bs-custom-class="big-tooltips"
                    title="Quote timestamp: {{ $item['quote_timestamp_formatted']
                                            }}">
                    {!! $item['current_unit_price_in_trade_currency_formatted'] !!}
                    @if(!empty($item['pre_market_price']))
                    <br />
                    <span class="badge rounded-pill bg-info">pre-market</span>
                    @endif
                    @if(!empty($item['post_market_price']))
                    <br />
                    <span class="badge rounded-pill bg-info">post-market</span>
                    @endif
                </td>
                <td class="text-right text-nowrap"
                    data-order="{{
                        $item['day_change_in_account_currency'] }}">
                    {!! $item['day_change_in_account_currency_formatted'] !!}
                    @if(!empty($item['pre_market_day_change']))
                    <br />
                    <span class="badge rounded-pill bg-info">pre-market</span>
                    @endif
                    @if(!empty($item['post_market_day_change']))
                    <br />
                    <span class="badge rounded-pill bg-info">post-market</span>
                    @endif
                </td>
                <td class="text-right text-nowrap"
                    data-order="{{
                        $item['day_change_percentage'] }}">
                    {!! $item['day_change_in_percentage_formatted'] !!}
                    @if(!empty($item['pre_market_day_change_percentage']))
                    <br />
                    <span class="badge rounded-pill bg-info">pre-market</span>
                    @endif
                    @if(!empty($item['post_market_day_change_percentage']))
                    <br />
                    <span class="badge rounded-pill bg-info">post-market</span>
                    @endif
                </td>
                <td class="text-right text-nowrap"
                    data-order="{{
                        $item['overall_change_in_account_currency'] }}">
                    {!! $item['overall_change_in_account_currency_formatted'] !!}
                    @if($item['overall_change2_in_account_currency_formatted'])
                    <br />
                    <span data-bs-toggle="tooltip"
                        title="Value without factoring any gains from
                                selling actions!"
                        style="font-style:italic">
                        {!! $item['overall_change2_in_account_currency_formatted']
                         !!}
                    </span>
                    @endif
                    @if(!empty($item['pre_market_price']))
                    <br />
                    <span class="badge rounded-pill bg-info">pre-market</span>
                    @endif
                    @if(!empty($item['post_market_price']))
                    <br />
                    <span class="badge rounded-pill bg-info">post-market</span>
                    @endif
                </td>
                <td class="text-right text-nowrap"
                    data-order="{{
                        $item['overall_change_in_percentage'] }}">
                    {!! $item['overall_change_in_percentage_formatted'] !!}
                    @if($item['overall_change2_in_percentage_formatted'])
                    <br />
                    <span data-bs-toggle="tooltip"
                        title="Value without factoring any gains from
                                selling actions!"
                        style="font-style:italic">
                        {!! $item['overall_change2_in_percentage_formatted'] !!}
                    </span>
                    @endif
                    @if(!empty($item['pre_market_price']))
                    <br />
                    <span class="badge rounded-pill bg-info">pre-market</span>
                    @endif
                    @if(!empty($item['post_market_price']))
                    <br />
                    <span class="badge rounded-pill bg-info">post-market</span>
                    @endif
                </td>
            </tr>
            @endforeach
        @endif
        </tbody>
        <tfoot class="tfoot">
            <tr>
                <td colspan="3"></td>
                <td class="text-right">
                    <div class="font-weight-bold text-nowrap" data-bs-toggle="tooltip"
                        title="Total Cost in account currency">
                        Total Cost:
                    </div>
                    <div class="text-nowrap">
                        {!! $accountData[$accountId]['total_cost_formatted'] !!}
                    </div>
                </td>
                <td class="text-right">
                    <div class="font-weight-bold text-nowrap" data-bs-toggle="tooltip"
                        title="Total Current Market Value in account currency">
                        Total MValue:
                    </div>
                    <div class="text-nowrap">
                        {!! $accountData[$accountId]['total_market_value_formatted'] !!}
                    </div>
                </td>
                <td colspan="4"></td>
                <td class="text-right">
                    <div class="font-weight-bold text-nowrap" data-bs-toggle="tooltip"
                        title="Total Overall Gain in account currency">
                        Total Gain:
                    </div>
                    <div class="text-nowrap">
                        {!! $accountData[$accountId]['total_change_formatted'] !!}
                    </div>
                </td>
                <td colspan="1"></td>
            </tr>
            <tr>
                <td colspan="4"></td>
                <td class="text-right">
                    <div class="font-weight-bold text-nowrap" data-bs-toggle="tooltip"
                        title="Total Cash & Cash Alternatives in Account Currency">
                        Total Cash:
                    </div>
                    <div class="text-nowrap">
                        {!! $accountData[$accountId]['cashBalanceUtils']
                                ->getFormattedAmount() !!}
                    </div>
                </td>
                <td colspan="6" class="text-left align-text-top">
                    {!! $accountData[$accountId]['cashBalanceUtils']
                            ->getFormattedDetails() !!}
                </td>
            </tr>
            <tr>
                <td colspan="11" class="position-relative">
                    <div class="chart-accountOverview"
                        data-account_id="{{ $accountId }}"
                        data-account_currency_iso_code="{{
                            $accountData[$accountId]['accountModel']
                                ->currency->iso_code }}"></div>
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="clearfix mb-3"></div>
</div>


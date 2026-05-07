@use('ovidiuro\myfinance2\App\Services\FinanceAPI')
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
                    title="Current market value in account currency">MValue</th>
                <th class="text-right text-nowrap" data-bs-toggle="tooltip"
                    title="Cost Basis in account currency: actual purchase cost. For positions with past sells, a second row (italic) shows Effective Cost (net of sell proceeds).">Cost Basis</th>
                <th class="no-sort text-right text-nowrap"
                    data-bs-toggle="tooltip"
                    title="Avg cost per share: actual average purchase price. For positions with past sells, a second row (italic) shows Effective Avg Cost (net of sell proceeds).">Avg Cost</th>
                <th class="no-sort text-right text-nowrap"
                    data-bs-toggle="tooltip"
                    title="Current unit price in trade currency">Price</th>
                <th class="text-right text-nowrap" data-bs-toggle="tooltip"
                    title="Day gain in account currency">Day Gain</th>
                <th class="text-right text-nowrap" data-bs-toggle="tooltip"
                    title="Day gain in percentage">Day Gain (%)</th>
                <th class="text-right text-nowrap" data-bs-toggle="tooltip"
                    title="Unrealized gain on currently held shares in account currency. For positions with past sells, a second row (italic) shows Total Return including realized gains.">Gain</th>
                <th class="text-right text-nowrap" data-bs-toggle="tooltip"
                    title="Unrealized gain % on currently held shares. For positions with past sells, a second row (italic) shows Total Return % including realized gains.">Gain (%)</th>
            </tr>
        </thead>
        <tbody class="table-body">
        @if(count($items) > 0)
            @foreach($items as $item)
            <tr>
                <td class="text-nowrap" data-bs-toggle="tooltip"
                    title="{!! $item['symbol_name'] !!}">
                    @if (!FinanceAPI::isUnlisted($item['symbol']))
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
                <td class="text-right text-nowrap" data-bs-toggle="tooltip"
                    data-bs-custom-class="big-tooltips"
                    title="Quote timestamp: {{ $item['quote_timestamp_formatted'] }}">
                    {!! $item['market_value_in_account_currency_formatted'] !!}
                </td>
                <td class="text-right text-nowrap">
                    @if($item['cost2_in_account_currency_formatted'])
                    {!! $item['cost2_in_account_currency_formatted'] !!}
                    <br />
                    <span class="fst-italic" style="opacity: 0.55"
                          data-bs-toggle="tooltip"
                          title="Effective Cost: net cash deployed (total amount invested minus proceeds collected from sales).{{ ($item['cost_in_account_currency'] ?? 0) < 0 ? ' Negative here: sell proceeds exceeded total buy cost, so remaining shares are effectively free.' : '' }}">
                        {!! $item['cost_in_account_currency_formatted'] !!}
                    </span>
                    @else
                    @if(($item['cost_in_account_currency'] ?? 0) < 0)
                    <i class="fas fa-info-circle text-muted ms-1"
                       data-bs-toggle="tooltip"
                       title="Total cost is negative because your sell proceeds exceeded your total buy cost for this position. Your remaining shares are effectively free, as you have already recouped more than your full investment."></i>
                    @endif
                    {!! $item['cost_in_account_currency_formatted'] !!}
                    @endif
                </td>
                <td class="text-right text-nowrap">
                    @if($item['average_unit_cost2_in_trade_currency_formatted'])
                    {!! $item['average_unit_cost2_in_trade_currency_formatted'] !!}
                    <br />
                    <span class="fst-italic" style="opacity: 0.55"
                          data-bs-toggle="tooltip"
                          title="Effective Avg Cost: per-share equivalent of net cash deployed, reduced by sell proceeds.{{ ($item['average_unit_cost_in_trade_currency'] ?? 0) < 0 ? ' Negative here: sell proceeds exceeded total buy cost, so remaining shares are effectively free.' : '' }}">
                        {!! $item['average_unit_cost_in_trade_currency_formatted'] !!}
                    </span>
                    @else
                    @if(($item['average_unit_cost_in_trade_currency'] ?? 0) < 0)
                    <i class="fas fa-info-circle text-muted ms-1"
                       data-bs-toggle="tooltip"
                       title="Average cost is negative because your sell proceeds exceeded
                           your total buy cost for this position. Your remaining shares are
                           effectively free. You have already recouped more than your full
                           investment."></i>
                    @endif
                    {!! $item['average_unit_cost_in_trade_currency_formatted'] !!}
                    @endif
                </td>
                <td class="text-right text-nowrap" data-bs-toggle="tooltip"
                    data-bs-custom-class="big-tooltips"
                    title="Quote timestamp: {{ $item['quote_timestamp_formatted'] }}">
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
                    data-order="{{ $item['overall_change2_in_account_currency_formatted']
                        ? $item['overall_change2_in_account_currency']
                        : $item['overall_change_in_account_currency'] }}">
                    @if($item['overall_change2_in_account_currency_formatted'])
                    {!! $item['overall_change2_in_account_currency_formatted'] !!}
                    <br />
                    <span class="fst-italic" style="opacity: 0.55"
                          data-bs-toggle="tooltip"
                          title="Total return including gains already realized from past sales.">
                        {!! $item['overall_change_in_account_currency_formatted'] !!}
                    </span>
                    @else
                    {!! $item['overall_change_in_account_currency_formatted'] !!}
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
                    data-order="{{ $item['overall_change2_in_percentage'] ?? $item['overall_change_in_percentage'] }}">
                    @if($item['overall_change2_in_percentage_formatted'])
                    {!! $item['overall_change2_in_percentage_formatted'] !!}
                    <br />
                    <span class="fst-italic" style="opacity: 0.55"
                          data-bs-toggle="tooltip"
                          title="Total return % including gains already realized from past sales.">
                        {!! $item['overall_change_in_percentage_formatted'] !!}
                        @if($item['overall_change_in_percentage'] === null && $item['quantity'])
                        <i class="fas fa-info-circle ms-1"
                           data-bs-toggle="tooltip"
                           title="N/A: effective cost is negative (sell proceeds exceeded total buy cost), so a percentage cannot be meaningfully calculated."></i>
                        @endif
                    </span>
                    @else
                    {!! $item['overall_change_in_percentage_formatted'] !!}
                    @if($item['overall_change_in_percentage'] === null && $item['quantity'])
                    <i class="fas fa-info-circle text-muted ms-1"
                       data-bs-toggle="tooltip"
                       title="N/A: effective cost is negative (sell proceeds exceeded total buy cost), so a percentage cannot be meaningfully calculated."></i>
                    @endif
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
                        title="Total Current Market Value in account currency">
                        Total MValue:
                    </div>
                    <div class="text-nowrap">
                        {!! $accountData[$accountId]['total_market_value_formatted'] !!}
                    </div>
                </td>
                <td class="text-right">
                    <div class="font-weight-bold text-nowrap" data-bs-toggle="tooltip"
                        title="Total Cost in account currency">
                        Total Cost:
                    </div>
                    <div class="text-nowrap">
                        {!! $accountData[$accountId]['total_cost_formatted'] !!}
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

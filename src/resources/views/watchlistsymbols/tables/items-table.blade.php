@php use ovidiuro\myfinance2\App\Services\MoneyFormat; @endphp
<style>
    .open-positions {
        padding: 0.35rem 0.6rem;
    }
    .open-positions .trades td {
        padding: 0.1rem 0.3rem;
    }
</style>
<div class="table-responsive">
    <table class="table table-sm data-table watchlist-symbol-items-table
                  table-hover">
        <thead class="thead">
            <tr role="row">
                <th class="text-nowrap">Symbol</th>
                <th class="text-right no-sort text-nowrap">Price</th>
                <th class="text-right text-nowrap">Day change</th>
                <th class="text-right no-sort text-nowrap">52-Wk range</th>
                <th class="text-right text-nowrap">% Above low</th>
                <th class="text-right text-nowrap">% Below high</th>
                <th class="no-sort text-nowrap">Open Positions</th>
                <th class="no-search no-sort">Orders</th>
                <th class="no-search no-sort">Alerts</th>
                <th class="no-search no-sort">Actions</th>
                <th class="no-search no-sort"></th>
            </tr>
        </thead>
        <tbody class="table-body">
        @if(count($items) > 0)
            @foreach($items as $symbol => $quoteData)
            <tr {!! count($quoteData['open_positions']) > 0
                        ? 'class="table-info"' : '' !!}>
                <td>
                    <div data-bs-toggle="tooltip" data-bs-placement="top"
                        data-bs-custom-class="big-tooltips" data-bs-html="true"
                        data-bs-title="<p class='text-left'>
Id: {{ $quoteData['item']->id }}<br />
Name: {!! !empty($quoteData['name']) ? $quoteData['name'] : $symbol !!}
Timestamp: {{ $quoteData['item']->timestamp }}<br />
Description: {{ $quoteData['item']->description }}<br />
Created: {{ $quoteData['item']->created_at }}<br />
Updated: {{ $quoteData['item']->updated_at }}</p>">
                        <a href="https://finance.yahoo.com/quote/{{ $symbol }}"
                            target="_blank">
                            {{ $symbol }}
                        </a>
                    </div>
                    @if (count($quoteData['open_positions']) > 0)
                        <i class="fa fa-briefcase" aria-hidden="true"
                            data-bs-toggle="tooltip" title="Has Open Positions"></i>
                    @endif
                </td>
                <td class="text-right">
                    <div data-bs-toggle="tooltip"
                        data-bs-custom-class="big-tooltips"
                        title="Quote timestamp: {{ $quoteData['quote_timestamp']
                            ->format(trans('myfinance2::general.datetime-format'))
                        }}">
                        {!! MoneyFormat
                        ::get_formatted_balance(
                            $quoteData['tradeCurrencyModel']->display_code,
                            $quoteData['price']
                        ) !!}
                    </div>
                    <div class="chart-symbol"
                        data-symbol="{{ $symbol }}"
                        data-symbol_name="{{ $quoteData['name'] }}"
                        data-base_value="{{ $quoteData['base_value'] }}"
                        data-trade_currency_formatted="{!!
                            $quoteData['tradeCurrencyModel']->display_code
                        !!}"
                        style="position: relative; float: right;"></div>
                    <div class="clearfix"></div>
                    <div>
                        @if(!empty($quoteData['pre_market_price']))
                        <span class="badge rounded-pill bg-info">pre-market</span>
                        @endif
                        @if(!empty($quoteData['post_market_price']))
                        <span class="badge rounded-pill bg-info">post-market</span>
                        @endif
                    </div>
                </td>
                <td class="text-right"
                    data-order="{{ $quoteData['day_change_percentage'] }}">
                    <div>
                        {!! MoneyFormat
                        ::get_formatted_balance_percentage(
                            $quoteData['day_change_percentage']
                        ) !!}
                    </div>
                    <div style="line-height: 24px">
                        {!! MoneyFormat
                        ::get_formatted_balance(
                            $quoteData['tradeCurrencyModel']->display_code,
                            $quoteData['day_change']
                        ) !!}
                    </div>
                    <div>
                        @if(!empty($quoteData['pre_market_day_change_percentage']))
                        <span class="badge rounded-pill bg-info">pre-market</span>
                        @endif
                        @if(!empty($quoteData['post_market_day_change_percentage']))
                        <span class="badge rounded-pill bg-info">post-market</span>
                        @endif
                    </div>
                </td>
                <td class="text-right">
                    <div class="text-nowrap">
                        {!! MoneyFormat
                        ::get_formatted_balance(
                            $quoteData['tradeCurrencyModel']->display_code,
                            $quoteData['fiftyTwoWeekLow']
                        ) !!}
                    </div>
                    <div class="text-nowrap">
                        {!! MoneyFormat
                        ::get_formatted_balance(
                            $quoteData['tradeCurrencyModel']->display_code,
                            $quoteData['fiftyTwoWeekHigh']
                        ) !!}
                    </div>
                </td>
                <td class="text-right"
                    data-order="{{ $quoteData['fiftyTwoWeekLowChangePercent']
                                    * 100 }}">
                    {!! MoneyFormat
                    ::get_formatted_52wk_low_percentage(
                        $quoteData['fiftyTwoWeekLowChangePercent'] * 100
                    ) !!}
                </td>
                <td class="text-right"
                    data-order="{{ - $quoteData['fiftyTwoWeekHighChangePercent']
                                    * 100 }}">
                    {!! MoneyFormat
                    ::get_formatted_52wk_high_percentage(
                        - $quoteData['fiftyTwoWeekHighChangePercent'] * 100,
                        count($quoteData['open_positions']) > 0
                    ) !!}
                </td>
                <td>
                @if(!empty($quoteData['open_positions']))
                    @foreach($quoteData['open_positions'] as $key => $openPosition)
                    <div class="row">
                        <div class="col-sm">
                            <div class="card">
                                <div class="card-body open-positions">
                                    @include('myfinance2::watchlistsymbols.'
                                        . 'tables.open-positions-card')
                                </div>
                            </div>
                        </div>
                    </div>
                    @if (array_key_last($quoteData['open_positions']) != $key)
                    <div class="clearfix mb-1"></div>
                    @endif
                    @endforeach
                @endif
                </td>
                <td>
                    <a class="btn btn-sm btn-outline-success w-100 mb-1"
                        href="{{ route('myfinance2::orders.create',
                                       ['symbol' => $symbol]) }}"
                        data-bs-toggle="tooltip"
                        title="Create Order for {{ $symbol }}">
                        Order <i class="fa fa-fw fa-plus-circle" aria-hidden="true"></i>
                    </a>
                    @if (!empty($quoteData['open_orders']))
                        @foreach ($quoteData['open_orders'] as $openOrder)
                        <a href="{{ route('myfinance2::orders.edit', $openOrder->id) }}"
                            class="d-block text-center w-100"
                            data-bs-toggle="tooltip"
                            title="Edit order for {{ $symbol }}">
                            <span class="badge d-block w-100 {{ $openOrder->getStatusBadgeClass() }}">
                                {{ $openOrder->status }}
                                {!! $openOrder->getFormattedLimitPrice() !!}
                            </span>
                        </a>
                        @endforeach
                    @endif
                </td>
                <td>
                    <a class="btn btn-sm btn-warning w-100 mb-1"
                        href="{{ route('myfinance2::price-alerts.create',
                                       ['symbol' => $symbol, 'source' => 'watchlist']) }}"
                        data-bs-toggle="tooltip"
                        title="Create Alert for {{ $symbol }}">
                        Alert <i class="fa fa-fw fa-plus-circle" aria-hidden="true"></i>
                    </a>
                    @if (!empty($quoteData['active_alerts']))
                        @foreach ($quoteData['active_alerts'] as $activeAlert)
                        <a href="{{ route('myfinance2::price-alerts.edit', $activeAlert->id) }}"
                            class="d-block text-center w-100"
                            data-bs-toggle="tooltip"
                            title="Edit alert for {{ $symbol }}">
                            <span class="badge d-block w-100 {{ $activeAlert->getStatusBadgeClass() }}">
                                {{ $activeAlert->alert_type === 'PRICE_ABOVE' ? '▲' : '▼' }}
                                {!! $quoteData['tradeCurrencyModel']->display_code !!}{{ MoneyFormat::get_formatted_price((float) $activeAlert->target_price) }}
                            </span>
                        </a>
                        @endforeach
                    @endif
                </td>
                <td>
                    <a class="btn btn-sm btn-outline-secondary w-100"
                        href="{{ route('myfinance2::watchlist-symbols.edit',
                                       $quoteData['item']->id) }}"
                        data-bs-toggle="tooltip"
                        title="{{ trans('myfinance2::general.tooltips.edit-item',
                                        ['type' => 'Watchlist Symbol']) }}">
                        {!! trans('myfinance2::general.buttons.edit') !!}
                    </a>
                </td>
                <td>
                    @include('myfinance2::watchlistsymbols.forms.delete-sm', [
                        'type' => 'Watchlist Symbol',
                        'id' => $quoteData['item']->id])
                </td>
            </tr>
            @endforeach
        @endif
        </tbody>
    </table>
    <div class="clearfix mb-3"></div>
</div>


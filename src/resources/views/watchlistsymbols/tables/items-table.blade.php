@php use ovidiuro\myfinance2\App\Services\MoneyFormat; @endphp
<style>
    .open-positions {
        padding: 0.35rem 0.6rem;
    }
    .open-positions .card-title {
        margin-bottom: 0.5rem;
    }
    .open-positions .trades td,
    .open-positions .metrics td {
        padding: 0.1rem 0.3rem;
    }
    @media (min-width: 1400px) {
        .open-positions-cards {
            max-width: 550px;
        }
    }
    .watchlist-symbol-items-table .symbol-perf-windows {
        --bs-table-bg: transparent;
    }
    .watchlist-symbol-items-table tbody tr.dt-row-hover > td {
        --bs-table-color-state: var(--bs-table-hover-color);
        --bs-table-bg-state: var(--bs-table-hover-bg);
    }
    .performance-row > td {
        border-top: none !important;
    }
    #non-watchlist-symbols-toggle {
        user-select: text;
    }
    .non-watchlist-group-header > tr > td {
        border-top: 2px solid var(--bs-table-border-color);
    }
    .watchlist-symbol-items-table th:nth-child(n+12),
    .watchlist-symbol-items-table td:nth-child(n+12) { display: none; }
    .watchlist-symbol-items-table tfoot input {
        min-width: 0;
        width: 100%;
        box-sizing: border-box;
    }

</style>
@php
    $watchlistItems    = array_filter($items, fn($q) => $q['is_on_watchlist']);
    $nonWatchlistItems = array_filter($items, fn($q) => !$q['is_on_watchlist']);

    $healthSymbolIndex = [];
    if (!empty($health_score)) {
        foreach (array_merge(
            $health_score['platinum_gold_symbols'] ?? [],
            $health_score['silver_symbols'] ?? [],
            $health_score['bronze_rust_symbols'] ?? [],
            $health_score['unrated_symbols'] ?? [],
        ) as $hsRow) {
            $healthSymbolIndex[$hsRow['symbol']] = $hsRow;
        }
    }
@endphp
<div class="table-responsive">
    <table class="table table-sm data-table watchlist-symbol-items-table">
        <thead class="thead">
            <tr role="row">
                <th class="text-nowrap">Symbol</th>
                <th class="text-right no-sort text-nowrap">Price</th>
                <th class="text-right text-nowrap">
                    <span data-bs-toggle="tooltip"
                          title="Price change today, in both currency and percentage.">Day Chg</span>
                </th>
                <th class="text-right no-sort text-nowrap">52W Range</th>
                <th class="text-right text-nowrap">
                    <span data-bs-toggle="tooltip"
                          title="How far the current price is above the 52-week low, as a percentage.">% Low</span>
                </th>
                <th class="text-right text-nowrap">
                    <span data-bs-toggle="tooltip"
                          title="How far the current price is below the 52-week high, as a percentage.">% High</span>
                </th>
                <th class="no-sort text-nowrap">Open Positions</th>
                <th class="no-search no-sort">Orders</th>
                <th class="no-search no-sort">Alerts</th>
                <th class="no-search no-sort">Actions</th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
            </tr>
        </thead>
        <tbody class="table-body">
        @if(count($watchlistItems) > 0)
            @foreach($watchlistItems as $symbol => $quoteData)
            @php
                $techInd = $quoteData['technical_indicators'] ?? null;
                $hasTechInd = !empty($techInd) && (
                    $techInd['rsi'] !== null || $techInd['analyst_target_price'] !== null
                    || $techInd['ma50'] !== null || $techInd['ma200'] !== null
                );
                $hasPerfRow = !empty($quoteData['performance']['has_data'])
                    || !empty($quoteData['performance']['sector'])
                    || $hasTechInd
                    || !empty($quoteData['categorization']);
                // Sort keys and filter labels are built in the BE (WatchlistTableMetaBuilder).
                $meta = $quoteData['table_meta'] ?? [];
            @endphp
            <tr data-symbol="{{ $symbol }}"
                data-tier="{{ $meta['tier_text'] ?? '' }}"
                data-quadrants="{{ json_encode($meta['quadrant_labels'] ?? []) }}"
                data-actions="{{ json_encode($meta['action_labels'] ?? []) }}"
                @if(count($quoteData['open_positions']) > 0) class="table-info"@endif>
                <td>
                    <a href="https://finance.yahoo.com/quote/{{ $symbol }}"
                        target="_blank"
                        data-bs-toggle="tooltip" data-bs-placement="top"
                        data-bs-custom-class="big-tooltips" data-bs-html="true"
                        data-bs-title="<p class='text-left'>
Id: {{ $quoteData['item']->id }}<br />
Name: {!! !empty($quoteData['name']) ? $quoteData['name'] : $symbol !!}
Timestamp: {{ $quoteData['item']->timestamp }}<br />
Description: {{ $quoteData['item']->description }}<br />
Created: {{ $quoteData['item']->created_at }}<br />
Updated: {{ $quoteData['item']->updated_at }}</p>">
                        {{ $symbol }}
                    </a>
                </td>
                <td class="text-right">
                    @php
                        $wl_qtFormatted = ($quoteData['quote_timestamp'] ?? null) instanceof \DateTime
                            ? $quoteData['quote_timestamp']->format(trans('myfinance2::general.datetime-format'))
                            : null;
                        $wl_regTsFormatted = ($quoteData['regular_market_timestamp'] ?? null) instanceof \DateTime
                            ? $quoteData['regular_market_timestamp']->format(trans('myfinance2::general.datetime-format'))
                            : null;
                        $wl_priceContent = MoneyFormat::get_formatted_balance(
                            $quoteData['tradeCurrencyModel']->display_code, $quoteData['price']
                        );
                    @endphp
                    @include('myfinance2::partials.price-tooltip', [
                        'currency'           => $quoteData['tradeCurrencyModel']->display_code,
                        'price'              => $quoteData['price'],
                        'timestamp'          => $wl_qtFormatted,
                        'isPreMarket'        => !empty($quoteData['pre_market_price']),
                        'isPostMarket'       => !empty($quoteData['post_market_price']),
                        'dayChange'          => $quoteData['day_change'] ?? null,
                        'dayChangePct'       => $quoteData['day_change_percentage'] ?? null,
                        'regularPrice'       => $quoteData['regular_market_price'] ?? null,
                        'regularTimestamp'   => $wl_regTsFormatted,
                        'regularDayChange'   => $quoteData['regular_market_day_change'] ?? null,
                        'regularDayChangePct'=> $quoteData['regular_market_day_change_pct'] ?? null,
                        'content'            => $wl_priceContent,
                    ])<br>
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
                <td class="text-right text-nowrap"
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
                <td class="text-right text-nowrap"
                    data-order="{{ $quoteData['fiftyTwoWeekLowChangePercent']
                                    * 100 }}">
                    {!! MoneyFormat
                    ::get_formatted_52wk_low_percentage(
                        $quoteData['fiftyTwoWeekLowChangePercent'] * 100
                    ) !!}
                </td>
                <td class="text-right text-nowrap"
                    data-order="{{ - $quoteData['fiftyTwoWeekHighChangePercent']
                                    * 100 }}">
                    {!! MoneyFormat
                    ::get_formatted_52wk_high_percentage(
                        - $quoteData['fiftyTwoWeekHighChangePercent'] * 100,
                        count($quoteData['open_positions']) > 0
                    ) !!}
                </td>
                <td{!! $hasPerfRow ? ' rowspan="2"' : '' !!}>
                @if(!empty($quoteData['open_positions']))
                    <div class="d-flex open-positions-cards gap-2">
                    @foreach($quoteData['open_positions'] as $openPosition)
                        <div class="card">
                            <div class="card-body open-positions">
                                @include('myfinance2::watchlistsymbols.'
                                    . 'tables.open-positions-card')
                            </div>
                        </div>
                    @endforeach
                    </div>
                @endif
                </td>
                <td{!! $hasPerfRow ? ' rowspan="2"' : '' !!}>
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
                <td{!! $hasPerfRow ? ' rowspan="2"' : '' !!}>
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
                            class="d-block mt-1"
                            data-bs-toggle="tooltip"
                            title="Edit alert for {{ $symbol }}">
                            <span class="badge d-block w-100 text-center {{ $activeAlert->getAlertTypeBadgeClass() }}">
                                {{ $activeAlert->alert_type === 'PRICE_ABOVE' ? '▲ Above' : '▼ Below' }}
                                <br>{!! MoneyFormat::get_formatted_price_display(
                                    $quoteData['tradeCurrencyModel']->display_code,
                                    (float) $activeAlert->target_price,
                                    true
                                ) !!}
                            </span>
                        </a>
                        @endforeach
                    @endif
                </td>
                <td{!! $hasPerfRow ? ' rowspan="2"' : '' !!}>
                    <a class="btn btn-sm btn-outline-secondary w-100"
                        href="{{ route('myfinance2::watchlist-symbols.edit',
                                       $quoteData['item']->id) }}"
                        data-bs-toggle="tooltip"
                        title="{{ trans('myfinance2::general.tooltips.edit-item',
                                        ['type' => 'Watchlist Symbol']) }}">
                        {!! trans('myfinance2::general.buttons.edit') !!}
                    </a>
                </td>
                <td{!! $hasPerfRow ? ' rowspan="2"' : '' !!}>
                    @include('myfinance2::watchlistsymbols.forms.delete-sm', [
                        'type' => 'Watchlist Symbol',
                        'id' => $quoteData['item']->id])
                </td>
                <td data-order="{{ $meta['gain_y_order'] ?? -9999999 }}"></td>
                <td data-order="{{ $meta['gain_y_pct_order'] ?? -9999999 }}"></td>
            </tr>
            @if ($hasPerfRow)
            <tr class="performance-row{{ count($quoteData['open_positions']) > 0 ? ' table-info' : '' }}" data-symbol="{{ $symbol }}">
                <td colspan="6" class="pt-1 pb-2">
                    @include('myfinance2::general.partials.symbol-performance', [
                        'symbolPerf'           => $quoteData['performance'],
                        'tradeCurrencyCode'    => $quoteData['tradeCurrencyModel']->iso_code,
                        'technicalIndicators'  => $hasTechInd ? $techInd : null,
                    ])
                    @include('myfinance2::watchlistsymbols.tables.partials.tier-quadrant-perf-row')
                </td>
            </tr>
            @endif
            @endforeach
        @endif
        </tbody>
        <tfoot class="tfoot">
            <tr role="row">
                <th class="text-nowrap">Symbol</th>
                <th class="text-right no-sort text-nowrap">Price</th>
                <th class="text-right text-nowrap">Day Chg</th>
                <th class="text-right no-sort text-nowrap">52W Range</th>
                <th class="text-right text-nowrap">% Low</th>
                <th class="text-right text-nowrap">% High</th>
                <th class="no-sort text-nowrap">Open Positions</th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
                <th class="no-search no-sort"></th>
            </tr>
        </tfoot>
    </table>
    <div class="clearfix mb-3"></div>
</div>

{{-- ── Non-watchlist traded symbols (collapsed) ── --}}
@if (count($nonWatchlistItems) > 0)
<template id="non-watchlist-template">
    <tbody class="non-watchlist-group-header">
        <tr>
            <td colspan="11" class="py-2">
                <button id="non-watchlist-symbols-toggle"
                        class="btn btn-link text-decoration-none p-0 fw-semibold"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#non-watchlist-symbols"
                        aria-expanded="false"
                        aria-controls="non-watchlist-symbols">
                    Traded symbols not on watchlist
                    <span class="badge bg-secondary ms-1">{{ count($nonWatchlistItems) }}</span>
                    <i class="fa fa-chevron-down ms-1 small" aria-hidden="true"></i>
                </button>
            </td>
        </tr>
    </tbody>
    <tbody id="non-watchlist-symbols" class="collapse">
    @foreach($nonWatchlistItems as $symbol => $quoteData)
    @php
        $nwTechInd = $quoteData['technical_indicators'] ?? null;
        $hasNwTechInd = !empty($nwTechInd) && (
            $nwTechInd['rsi'] !== null || $nwTechInd['analyst_target_price'] !== null
            || $nwTechInd['ma50'] !== null || $nwTechInd['ma200'] !== null
        );
        $hasNwPerfRow = !empty($quoteData['performance']['has_data'])
            || !empty($quoteData['performance']['sector'])
            || $hasNwTechInd
            || !empty($quoteData['categorization']);
    @endphp
    <tr data-symbol="{{ $symbol }}-nw"@if(count($quoteData['open_positions']) > 0) class="table-info"@endif>
        <td>
            <a href="https://finance.yahoo.com/quote/{{ $symbol }}"
                target="_blank">{{ $symbol }}</a>
        </td>
        <td class="text-right">
            {!! MoneyFormat::get_formatted_balance(
                $quoteData['tradeCurrencyModel']->display_code,
                $quoteData['price']
            ) !!}
        </td>
        <td class="text-right text-nowrap">
            {!! MoneyFormat::get_formatted_balance_percentage(
                $quoteData['day_change_percentage']
            ) !!}
        </td>
        <td class="text-right">
            <div class="text-nowrap">
                {!! MoneyFormat::get_formatted_balance(
                    $quoteData['tradeCurrencyModel']->display_code,
                    $quoteData['fiftyTwoWeekLow']
                ) !!}
            </div>
            <div class="text-nowrap">
                {!! MoneyFormat::get_formatted_balance(
                    $quoteData['tradeCurrencyModel']->display_code,
                    $quoteData['fiftyTwoWeekHigh']
                ) !!}
            </div>
        </td>
        <td class="text-right text-nowrap">
            {!! MoneyFormat::get_formatted_52wk_low_percentage(
                $quoteData['fiftyTwoWeekLowChangePercent'] * 100
            ) !!}
        </td>
        <td class="text-right text-nowrap">
            {!! MoneyFormat::get_formatted_52wk_high_percentage(
                - $quoteData['fiftyTwoWeekHighChangePercent'] * 100,
                count($quoteData['open_positions']) > 0
            ) !!}
        </td>
        <td{!! $hasNwPerfRow ? ' rowspan="2"' : '' !!}>
        @if(!empty($quoteData['open_positions']))
            <div class="d-flex open-positions-cards gap-2">
            @foreach($quoteData['open_positions'] as $openPosition)
                <div class="card">
                    <div class="card-body open-positions">
                        @include('myfinance2::watchlistsymbols.tables.open-positions-card')
                    </div>
                </div>
            @endforeach
            </div>
        @endif
        </td>
        <td{!! $hasNwPerfRow ? ' rowspan="2"' : '' !!}></td>
        <td{!! $hasNwPerfRow ? ' rowspan="2"' : '' !!}></td>
        <td{!! $hasNwPerfRow ? ' rowspan="2"' : '' !!} colspan="6" class="text-end">
            <a class="btn btn-sm btn-outline-primary"
                href="{{ route('myfinance2::watchlist-symbols.create',
                               ['symbol' => $symbol]) }}"
                data-bs-toggle="tooltip"
                title="Add {{ $symbol }} to watchlist">
                Add to watchlist
                <i class="fa fa-fw fa-plus-circle" aria-hidden="true"></i>
            </a>
        </td>
    </tr>
    @if ($hasNwPerfRow)
    <tr class="performance-row-nw">
        <td colspan="6" class="pt-1 pb-2">
            @include('myfinance2::general.partials.symbol-performance', [
                'symbolPerf'          => $quoteData['performance'],
                'tradeCurrencyCode'   => $quoteData['tradeCurrencyModel']->iso_code,
                'technicalIndicators' => $hasNwTechInd ? $nwTechInd : null,
            ])
            @include('myfinance2::watchlistsymbols.tables.partials.tier-quadrant-perf-row')
        </td>
    </tr>
    @endif
    @endforeach
    </tbody>
</template>
@endif

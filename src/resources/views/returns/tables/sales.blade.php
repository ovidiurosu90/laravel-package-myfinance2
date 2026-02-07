{{-- Stock Sales (Sells) --}}
@php
    $excludedSalesCount = collect($data['excludedTrades'])
        ->filter(function($trade) { return $trade['action'] === 'SELL'; })
        ->count();
    $hasSalesToShow = count($data['sales']['items']) > 0 || $excludedSalesCount > 0;
@endphp
<tr>
    <td class="fw-bold">
        {{ trans('myfinance2::returns.labels.stock-sales') }}
        @if($hasSalesToShow)
            <button class="btn btn-sm btn-link p-0"
                data-bs-toggle="collapse"
                data-bs-target="#sales-{{ $accountId }}">
                @if(count($data['sales']['items']) > 0 && $excludedSalesCount > 0)
                    ({{ count($data['sales']['items']) }} trades + {{ $excludedSalesCount }} excluded)
                @elseif(count($data['sales']['items']) > 0)
                    ({{ count($data['sales']['items']) }} trades)
                @else
                    ({{ $excludedSalesCount }} excluded)
                @endif
            </button>
        @endif
    </td>
    <td class="currency-value"
        data-eur="{{ $data['sales']['totals']['EUR']['principalAmountFormatted'] }}"
        data-usd="{{ $data['sales']['totals']['USD']['principalAmountFormatted'] }}"
        data-eur-fees="{{ $data['sales']['totals']['EUR']['feesFormatted'] }}"
        data-usd-fees="{{ $data['sales']['totals']['USD']['feesFormatted'] }}"
        data-eur-fees-text="{{ $data['sales']['totals']['EUR']['feesText'] }}"
        data-usd-fees-text="{{ $data['sales']['totals']['USD']['feesText'] }}">
        @php
            $principalValue = $selectedCurrency === 'EUR'
                ? $data['sales']['totals']['EUR']['principalAmountFormatted']
                : $data['sales']['totals']['USD']['principalAmountFormatted'];
        @endphp
        <div>
            {!! $principalValue !!}
            @if($selectedCurrency === 'EUR' && !empty($data['sales']['totals']['EUR']['feesText']))
                <span style="font-size: 0.85rem; color: #6c757d; margin-left: 0.25rem;" class="fees-text">
                    {!! $data['sales']['totals']['EUR']['feesText'] !!}
                </span>
            @elseif($selectedCurrency === 'USD' && !empty($data['sales']['totals']['USD']['feesText']))
                <span style="font-size: 0.85rem; color: #6c757d; margin-left: 0.25rem;" class="fees-text">
                    {!! $data['sales']['totals']['USD']['feesText'] !!}
                </span>
            @endif
        </div>
    </td>
</tr>
@if($hasSalesToShow)
<tr class="collapse" id="sales-{{ $accountId }}">
    <td colspan="2">
        <table class="table table-sm table-striped mb-0">
            @if(count($data['sales']['items']) > 0)
            <thead>
                <tr class="small">
                    <th>Date</th>
                    <th>Symbol</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-end">Unit Price</th>
                    <th class="text-end">Principal Amount in {{ $selectedCurrency }}</th>
                    <th class="text-end">
                        Fee in {{ $selectedCurrency }}
                        @php
                            $feeConvertedInfo = 'Fee converted to display currency using the same '
                                . 'exchange rate as Principal Amount.';
                        @endphp
                        <i class="fa fa-info-circle fa-fw"
                            style="margin-left: 0.25rem;"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            data-bs-title="{{ $feeConvertedInfo }}"></i>
                    </th>
                    <th class="text-end">
                        Fee
                        @php
                            $feeInfo = 'Fee is always in account currency and never changes with '
                                . 'currency toggle. It is informative only and not used in the '
                                . 'returns calculation.';
                        @endphp
                        <i class="fa fa-info-circle fa-fw"
                            style="margin-left: 0.25rem;"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            data-bs-title="{{ $feeInfo }}"></i>
                    </th>
                </tr>
            </thead>
            @endif
            <tbody>
                @foreach($data['sales']['items'] as $sale)
                    <tr class="small">
                        <td class="text-nowrap">{{ $sale['date'] }}</td>
                        <td class="text-nowrap">{{ $sale['symbol'] }}</td>
                        <td class="text-end">{{ $sale['quantityFormatted'] }}</td>
                        <td class="text-end text-nowrap">
                            {!! $sale['unitPriceFormatted'] !!}
                        </td>
                        @php
                            $tooltipEUR = "EURUSD: {$sale['EUR']['eurusdRate']}<br>"
                                . "{$sale['EUR']['conversionPair']}: "
                                . "{$sale['EUR']['conversionExchangeRateClean']}";
                            $tooltipUSD = "EURUSD: {$sale['USD']['eurusdRate']}<br>"
                                . "{$sale['USD']['conversionPair']}: "
                                . "{$sale['USD']['conversionExchangeRateClean']}";
                            $principalAmount = $selectedCurrency === 'EUR'
                                ? $sale['EUR']['principalAmountFormatted']
                                : $sale['USD']['principalAmountFormatted'];
                            $showWarning = ($selectedCurrency === 'EUR' && $sale['EUR']['showMissingRateWarning'])
                                || ($selectedCurrency === 'USD' && $sale['USD']['showMissingRateWarning'])
                                ? 'inline' : 'none';
                        @endphp
                        <td class="text-end text-nowrap currency-value"
                            data-eur="{{ $sale['EUR']['principalAmountFormatted'] }}"
                            data-usd="{{ $sale['USD']['principalAmountFormatted'] }}"
                            data-eur-value="{{ $sale['EUR']['principalAmount'] }}"
                            data-usd-value="{{ $sale['USD']['principalAmount'] }}"
                            data-eur-tooltip="{{ $tooltipEUR }}"
                            data-usd-tooltip="{{ $tooltipUSD }}"
                            data-eur-show-warning="{{ $sale['EUR']['showMissingRateWarning'] ? 'true' : 'false' }}"
                            data-usd-show-warning="{{ $sale['USD']['showMissingRateWarning'] ? 'true' : 'false' }}">
                            <span data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-custom-class="big-tooltips"
                                data-bs-html="true"
                                data-bs-title="{{ $tooltipEUR }}"
                                class="principal-amount-value">
                            {!! $principalAmount !!}</span>
                            <i class="fa-solid fa-circle-info ms-1 sale-warning-icon"
                                style="font-size: 0.75rem; color: black; display: {{ $showWarning }};"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-title="Exchange rate was fetched from API (not stored in trade entry)"></i>
                        </td>
                        @php
                            $feeValue = $selectedCurrency === 'EUR'
                                ? $sale['EUR']['feeFormatted']
                                : $sale['USD']['feeFormatted'];
                        @endphp
                        <td class="text-end text-nowrap currency-value"
                            data-eur="{{ $sale['EUR']['feeFormatted'] }}"
                            data-usd="{{ $sale['USD']['feeFormatted'] }}"
                            data-eur-tooltip="{{ $tooltipEUR }}"
                            data-usd-tooltip="{{ $tooltipUSD }}"
                            data-eur-show-warning="{{ $sale['EUR']['showMissingRateWarning'] ? 'true' : 'false' }}"
                            data-usd-show-warning="{{ $sale['USD']['showMissingRateWarning'] ? 'true' : 'false' }}">
                            @if($sale['EUR']['fee'] > 0 || $sale['USD']['fee'] > 0)
                                <span data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    data-bs-custom-class="big-tooltips"
                                    data-bs-html="true"
                                    data-bs-title="{{ $tooltipEUR }}">
                                <small>
                                    {!! $feeValue !!}
                                </small></span>
                            @endif
                        </td>
                        @php
                            $accountFee = $sale['accountCurrencyFee'] > 0
                                ? $sale['accountCurrencyFeeFormatted']
                                : '';
                        @endphp
                        <td class="text-end text-nowrap">
                            <small class="text-danger">
                                {!! $accountFee !!}
                            </small>
                        </td>
                    </tr>
                @endforeach
                @if(count($data['sales']['items']) > 0)
                    @php
                        $totalPrincipalValue = $selectedCurrency === 'EUR'
                            ? $data['sales']['totals']['EUR']['principalAmountGrossFormatted']
                            : $data['sales']['totals']['USD']['principalAmountGrossFormatted'];
                    @endphp
                    <tr class="small fw-bold">
                        <td colspan="4">Total Sales:</td>
                        <td class="text-end currency-value"
                            data-eur="{{ $data['sales']['totals']['EUR']['principalAmountGrossFormatted'] }}"
                            data-usd="{{ $data['sales']['totals']['USD']['principalAmountGrossFormatted'] }}"
                            data-eur-value="{{ $data['sales']['totals']['EUR']['principalAmountGross'] }}"
                            data-usd-value="{{ $data['sales']['totals']['USD']['principalAmountGross'] }}">
                            <span class="principal-amount-value">{!! $totalPrincipalValue !!}</span>
                        </td>
                        @php
                            $hasAnyFees = $data['sales']['totals']['EUR']['fees'] > 0
                                || $data['sales']['totals']['USD']['fees'] > 0;
                        @endphp
                        <td class="text-end currency-value"
                            data-eur="{{ $data['sales']['totals']['EUR']['feesFormatted'] }}"
                            data-usd="{{ $data['sales']['totals']['USD']['feesFormatted'] }}">
                            @if($selectedCurrency === 'EUR' && $hasAnyFees)
                                <span>{!! $data['sales']['totals']['EUR']['feesFormatted'] !!}</span>
                            @elseif($selectedCurrency === 'USD' && $hasAnyFees)
                                <span>{!! $data['sales']['totals']['USD']['feesFormatted'] !!}</span>
                            @endif
                        </td>
                        <td></td>
                    </tr>
                    @php
                        $hasExcludedFees = $data['sales']['totals']['EUR']['excludedFees'] > 0
                            || $data['sales']['totals']['USD']['excludedFees'] > 0;
                    @endphp
                    @if($hasExcludedFees)
                        @php
                            $excludedFeeValue = $selectedCurrency === 'EUR'
                                ? '+' . $data['sales']['totals']['EUR']['excludedFeesFormatted']
                                : '+' . $data['sales']['totals']['USD']['excludedFeesFormatted'];
                        @endphp
                        <tr class="small text-muted" style="background-color: #f8f9fa;">
                            <td colspan="5">
                                <small>
                                    <em>
                                        Excluded from net total (AutoFX Fee &amp; other hidden fees)
                                    </em>
                                </small>
                            </td>
                            <td class="text-end text-nowrap fw-bold currency-value" style="color: #dc3545;"
                                data-eur="+{{ $data['sales']['totals']['EUR']['excludedFeesFormatted'] }}"
                                data-usd="+{{ $data['sales']['totals']['USD']['excludedFeesFormatted'] }}">
                                <span>{!! $excludedFeeValue !!}</span>
                            </td>
                            <td></td>
                        </tr>
                    @endif
                @endif
                @php
                    $excludedSales = collect($data['excludedTrades'])
                        ->filter(function($trade) { return $trade['action'] === 'SELL'; });
                @endphp
                @if($excludedSales->count() > 0)
                    @if(count($data['sales']['items']) === 0)
                        {{-- Show header when only excluded sales exist --}}
                        <tr class="small">
                            <th>Date</th>
                            <th>Symbol</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Principal Amount</th>
                            <th class="text-end" colspan="2">Fee</th>
                        </tr>
                    @endif
                    <tr class="small text-muted border-top">
                        <td colspan="7" class="text-center py-2">
                            <small>
                                <em>
                                    Excluded from sales
                                    (not included in returns calculation)
                                </em>
                            </small>
                        </td>
                    </tr>
                    @foreach($excludedSales as $trade)
                        <tr class="small text-muted" style="background-color: #f8f9fa;">
                            <td class="text-nowrap">{{ $trade['date'] }}</td>
                            <td class="text-nowrap">{{ $trade['symbol'] }}</td>
                            <td class="text-end">{{ $trade['quantityFormatted'] }}</td>
                            <td class="text-end text-nowrap">
                                {!! $trade['unitPriceFormatted'] !!}
                            </td>
                            <td class="text-end text-nowrap">
                                {!! $trade['formatted'] !!}
                            </td>
                            <td class="text-end text-nowrap">
                                @if($trade['fee'] > 0)
                                    <small>
                                        {!! $trade['feeFormatted'] !!}
                                    </small>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if($trade['fee'] > 0)
                                    <small>{!! $trade['feeFormatted'] !!}</small>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
    </td>
</tr>
@endif

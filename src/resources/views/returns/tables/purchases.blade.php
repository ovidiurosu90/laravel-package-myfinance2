{{-- Stock Purchases (Buys) --}}
<tr>
    <td class="fw-bold">
        {{ trans('myfinance2::returns.labels.stock-purchases') }}
        @if(count($data['purchases']['items']) > 0)
            <button class="btn btn-sm btn-link p-0"
                data-bs-toggle="collapse"
                data-bs-target="#purchases-{{ $accountId }}">
                ({{ count($data['purchases']['items']) }} trades)
            </button>
        @endif
    </td>
    <td class="currency-value"
        data-eur="{{ $data['purchases']['totals']['EUR']['principalAmountFormatted'] }}"
        data-usd="{{ $data['purchases']['totals']['USD']['principalAmountFormatted'] }}"
        data-eur-fees="{{ $data['purchases']['totals']['EUR']['feesFormatted'] }}"
        data-usd-fees="{{ $data['purchases']['totals']['USD']['feesFormatted'] }}"
        data-eur-fees-text="{{ $data['purchases']['totals']['EUR']['feesText'] }}"
        data-usd-fees-text="{{ $data['purchases']['totals']['USD']['feesText'] }}">
        @php
            $principalValue = $selectedCurrency === 'EUR'
                ? $data['purchases']['totals']['EUR']['principalAmountFormatted']
                : $data['purchases']['totals']['USD']['principalAmountFormatted'];
        @endphp
        <div>
            {!! $principalValue !!}
            @if($selectedCurrency === 'EUR' && !empty($data['purchases']['totals']['EUR']['feesText']))
                <span style="font-size: 0.85rem; color: #6c757d; margin-left: 0.25rem;" class="fees-text">
                    {!! $data['purchases']['totals']['EUR']['feesText'] !!}
                </span>
            @elseif($selectedCurrency === 'USD' && !empty($data['purchases']['totals']['USD']['feesText']))
                <span style="font-size: 0.85rem; color: #6c757d; margin-left: 0.25rem;" class="fees-text">
                    {!! $data['purchases']['totals']['USD']['feesText'] !!}
                </span>
            @endif
        </div>
    </td>
</tr>
@if(count($data['purchases']['items']) > 0)
<tr class="collapse" id="purchases-{{ $accountId }}">
    <td colspan="2">
        <table class="table table-sm table-striped mb-0">
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
            <tbody>
                @foreach($data['purchases']['items'] as $purchase)
                    <tr class="small">
                        <td class="text-nowrap">{{ $purchase['date'] }}</td>
                        <td class="text-nowrap">{{ $purchase['symbol'] }}</td>
                        <td class="text-end">{{ $purchase['quantityFormatted'] }}</td>
                        <td class="text-end text-nowrap">
                            {!! $purchase['unitPriceFormatted'] !!}
                        </td>
                        @php
                            $tooltipEUR = "EURUSD: {$purchase['EUR']['eurusdRate']}<br>"
                                . "{$purchase['EUR']['conversionPair']}: "
                                . "{$purchase['EUR']['conversionExchangeRateClean']}";
                            $tooltipUSD = "EURUSD: {$purchase['USD']['eurusdRate']}<br>"
                                . "{$purchase['USD']['conversionPair']}: "
                                . "{$purchase['USD']['conversionExchangeRateClean']}";
                            $principalAmount = $selectedCurrency === 'EUR'
                                ? $purchase['EUR']['principalAmountFormatted']
                                : $purchase['USD']['principalAmountFormatted'];
                            $showWarning = ($selectedCurrency === 'EUR' && $purchase['EUR']['showMissingRateWarning'])
                                || ($selectedCurrency === 'USD' && $purchase['USD']['showMissingRateWarning'])
                                ? 'inline' : 'none';
                        @endphp
                        <td class="text-end text-nowrap currency-value"
                            data-eur="{{ $purchase['EUR']['principalAmountFormatted'] }}"
                            data-usd="{{ $purchase['USD']['principalAmountFormatted'] }}"
                            data-eur-value="{{ $purchase['EUR']['principalAmount'] }}"
                            data-usd-value="{{ $purchase['USD']['principalAmount'] }}"
                            data-eur-tooltip="{{ $tooltipEUR }}"
                            data-usd-tooltip="{{ $tooltipUSD }}"
                            data-eur-show-warning="{{ $purchase['EUR']['showMissingRateWarning'] ? 'true' : 'false' }}"
                            data-usd-show-warning="{{ $purchase['USD']['showMissingRateWarning'] ? 'true' : 'false' }}">
                            <span data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-custom-class="big-tooltips"
                                data-bs-html="true"
                                data-bs-title="{{ $tooltipEUR }}"
                                class="principal-amount-value">
                            {!! $principalAmount !!}</span>
                            <i class="fa-solid fa-circle-info ms-1 purchase-warning-icon"
                                style="font-size: 0.75rem; color: black; display: {{ $showWarning }};"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-title="Exchange rate was fetched from API (not stored in trade entry)"></i>
                        </td>
                        @php
                            $feeValue = $selectedCurrency === 'EUR'
                                ? $purchase['EUR']['feeFormatted']
                                : $purchase['USD']['feeFormatted'];
                        @endphp
                        <td class="text-end text-nowrap currency-value"
                            data-eur="{{ $purchase['EUR']['feeFormatted'] }}"
                            data-usd="{{ $purchase['USD']['feeFormatted'] }}"
                            data-eur-tooltip="{{ $tooltipEUR }}"
                            data-usd-tooltip="{{ $tooltipUSD }}"
                            data-eur-show-warning="{{ $purchase['EUR']['showMissingRateWarning'] ? 'true' : 'false' }}"
                            data-usd-show-warning="{{ $purchase['USD']['showMissingRateWarning'] ? 'true' : 'false' }}">
                            @if($purchase['EUR']['fee'] > 0 || $purchase['USD']['fee'] > 0)
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
                            $accountFee = $purchase['accountCurrencyFee'] > 0
                                ? $purchase['accountCurrencyFeeFormatted']
                                : '';
                        @endphp
                        <td class="text-end text-nowrap">
                            <small class="text-danger">
                                {!! $accountFee !!}
                            </small>
                        </td>
                    </tr>
                @endforeach
                @php
                    $totalPrincipalValue = $selectedCurrency === 'EUR'
                        ? $data['purchases']['totals']['EUR']['principalAmountGrossFormatted']
                        : $data['purchases']['totals']['USD']['principalAmountGrossFormatted'];
                @endphp
                <tr class="small fw-bold">
                    <td colspan="4">Total Purchases:</td>
                    <td class="text-end currency-value"
                        data-eur="{{ $data['purchases']['totals']['EUR']['principalAmountGrossFormatted'] }}"
                        data-usd="{{ $data['purchases']['totals']['USD']['principalAmountGrossFormatted'] }}"
                        data-eur-value="{{ $data['purchases']['totals']['EUR']['principalAmountGross'] }}"
                        data-usd-value="{{ $data['purchases']['totals']['USD']['principalAmountGross'] }}">
                        <span class="principal-amount-value">{!! $totalPrincipalValue !!}</span>
                    </td>
                    @php
                        $hasAnyFees = $data['purchases']['totals']['EUR']['fees'] > 0
                            || $data['purchases']['totals']['USD']['fees'] > 0;
                    @endphp
                    <td class="text-end currency-value"
                        data-eur="{{ $data['purchases']['totals']['EUR']['feesFormatted'] }}"
                        data-usd="{{ $data['purchases']['totals']['USD']['feesFormatted'] }}">
                        @if($selectedCurrency === 'EUR' && $hasAnyFees)
                            <span>{!! $data['purchases']['totals']['EUR']['feesFormatted'] !!}</span>
                        @elseif($selectedCurrency === 'USD' && $hasAnyFees)
                            <span>{!! $data['purchases']['totals']['USD']['feesFormatted'] !!}</span>
                        @endif
                    </td>
                    <td></td>
                </tr>
                @php
                    $hasExcludedFees = $data['purchases']['totals']['EUR']['excludedFees'] > 0
                        || $data['purchases']['totals']['USD']['excludedFees'] > 0;
                @endphp
                @if($hasExcludedFees)
                    @php
                        $excludedFeeValue = $selectedCurrency === 'EUR'
                            ? '-' . $data['purchases']['totals']['EUR']['excludedFeesFormatted']
                            : '-' . $data['purchases']['totals']['USD']['excludedFeesFormatted'];
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
                            data-eur="-{{ $data['purchases']['totals']['EUR']['excludedFeesFormatted'] }}"
                            data-usd="-{{ $data['purchases']['totals']['USD']['excludedFeesFormatted'] }}">
                            <span>{!! $excludedFeeValue !!}</span>
                        </td>
                        <td></td>
                    </tr>
                @endif
                @php
                    $excludedPurchases = collect($data['excludedTrades'])
                        ->filter(function($trade) { return $trade['action'] === 'BUY'; });
                @endphp
                @if($excludedPurchases->count() > 0)
                    <tr class="small text-muted border-top">
                        <td colspan="7" class="text-center py-2">
                            <small>
                                <em>
                                    Excluded from purchases
                                    (not included in returns calculation)
                                </em>
                            </small>
                        </td>
                    </tr>
                    @foreach($excludedPurchases as $trade)
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

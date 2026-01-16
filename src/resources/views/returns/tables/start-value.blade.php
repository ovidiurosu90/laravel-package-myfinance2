{{-- Jan 1 Value --}}
<tr>
    <td class="fw-bold">
        {{ trans('myfinance2::returns.labels.jan1-value') }}
    </td>
    @php
        $jan1Value = $selectedCurrency === 'EUR'
            ? $data['jan1Value']['EUR']['formatted']
            : $data['jan1Value']['USD']['formatted'];
    @endphp
    <td class="currency-value"
        data-eur="{{ $data['jan1Value']['EUR']['formatted'] }}"
        data-usd="{{ $data['jan1Value']['USD']['formatted'] }}">
        <span>{!! $jan1Value !!}</span>
    </td>
</tr>
<tr class="table-light">
    <td style="padding-left: 2rem;">
        <small class="text-muted">
            Positions
            @if(count($data['jan1PositionDetails']) > 0)
                (
                <button class="btn btn-sm btn-link p-0"
                    data-bs-toggle="collapse"
                    data-bs-target="#jan1-positions-{{ $accountId }}"
                    style="padding: 0 !important; margin: 0 !important; text-decoration: none;">
                    {{ count($data['jan1PositionDetails']) }}
                </button>
                )
            @endif
        </small>
    </td>
    @php
        $jan1PositionsValue = $selectedCurrency === 'EUR'
            ? $data['jan1PositionsValue']['EUR']['formatted']
            : $data['jan1PositionsValue']['USD']['formatted'];
    @endphp
    <td><small class="text-muted currency-value"
        data-eur="{{ $data['jan1PositionsValue']['EUR']['formatted'] }}"
        data-usd="{{ $data['jan1PositionsValue']['USD']['formatted'] }}">
        <span>{!! $jan1PositionsValue !!}</span>
    </small></td>
</tr>
@if(count($data['jan1PositionDetails']) > 0)
<tr class="collapse" id="jan1-positions-{{ $accountId }}">
    <td colspan="2">
        <table class="table table-sm table-striped mb-0">
            <thead>
                <tr class="small">
                    <th>Symbol</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-end">Price</th>
                    <th class="text-end">Local Value</th>
                    <th class="text-end">FX</th>
                    <th class="text-end">Value in EUR</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['jan1PositionDetails'] as $position)
                <tr class="small">
                    <td class="text-nowrap">{{ $position['symbol'] }}</td>
                    <td class="text-end">{{ $position['quantityFormatted'] }}</td>
                    <td class="text-end">
                        @if($position['priceOverridden'])
                            @php
                                $tooltipText = 'Price overridden from config';
                                $priceIconColor = 'text-warning';
                                if (!empty($position['apiPrice'])) {
                                    $tooltipText .= '<br />API value: '
                                        . $position['apiPriceFormatted'];
                                    $tooltipText .= '<br />Config value: '
                                        . $position['configPriceFormatted'];
                                    // Use pre-calculated percentage
                                    if ($position['priceDiffPercentage'] < 5) {
                                        $priceIconColor = 'text-dark';
                                    }
                                }
                                $priceTooltipAttr = "&lt;p class='text-left'&gt;"
                                    . $tooltipText . "&lt;/p&gt;";
                            @endphp
                            <i class="fa-solid fa-circle-info me-1 {{ $priceIconColor }}"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-custom-class="big-tooltips"
                                data-bs-html="true"
                                data-bs-title="{!! $priceTooltipAttr !!}">
                            </i>
                            {{ $position['priceFormatted'] }} {!! $position['tradeCurrencyDisplayCode'] !!}
                        @else
                            {{ $position['priceFormatted'] }} {!! $position['tradeCurrencyDisplayCode'] !!}
                        @endif
                    </td>
                    <td class="text-end">{!! $position['localMarketValueFormatted'] !!}</td>
                    <td class="text-end">
                        @if($position['exchangeRateOverridden'])
                            @php
                                $tooltipText = 'Exchange rate overridden from config';
                                $fxIconColor = 'text-warning';
                                if (!empty($position['apiExchangeRate'])) {
                                    $tooltipText .= '<br />API value: '
                                        . $position['apiExchangeRateFormatted'];
                                    $tooltipText .= '<br />Config value: '
                                        . $position['exchangeRateFormatted'];
                                    // Use pre-calculated percentage
                                    if ($position['fxDiffPercentage'] < 5) {
                                        $fxIconColor = 'text-dark';
                                    }
                                }
                                $fxTooltipAttr = "&lt;p class='text-left'&gt;"
                                    . $tooltipText . "&lt;/p&gt;";
                            @endphp
                            <i class="fa-solid fa-circle-info me-1 {{ $fxIconColor }}"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-custom-class="big-tooltips"
                                data-bs-html="true"
                                data-bs-title="{!! $fxTooltipAttr !!}"></i>
                            {{ $position['exchangeRateClean'] }}
                        @else
                            {{ $position['exchangeRateClean'] }}
                        @endif
                    </td>
                    @php
                        $tooltipEUR = "EURUSD: {$position['EUR']['eurusdRate']}<br>"
                            . "{$position['EUR']['conversionPair']}: "
                            . "{$position['EUR']['conversionExchangeRateClean']}";
                        $tooltipUSD = "EURUSD: {$position['USD']['eurusdRate']}<br>"
                            . "{$position['USD']['conversionPair']}: "
                            . "{$position['USD']['conversionExchangeRateClean']}";
                    @endphp
                    <td class="text-end currency-value"
                        data-eur="{{ $position['EUR']['marketValueFormatted'] }}"
                        data-usd="{{ $position['USD']['marketValueFormatted'] }}"
                        data-eur-tooltip="{{ $tooltipEUR }}"
                        data-usd-tooltip="{{ $tooltipUSD }}">
                        <span data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            data-bs-custom-class="big-tooltips"
                            data-bs-html="true"
                            data-bs-title="{{ $tooltipEUR }}">
                        {!! $position['EUR']['marketValueFormatted'] !!}</span>
                    </td>
                </tr>
                @endforeach
                @php
                    $totalPositionsValue = $selectedCurrency === 'EUR'
                        ? $data['jan1PositionsValue']['EUR']['formatted']
                        : $data['jan1PositionsValue']['USD']['formatted'];
                @endphp
                <tr class="table-light fw-bold small">
                    <td colspan="4"></td>
                    <td class="text-end">Total Positions:</td>
                    <td class="text-end currency-value"
                        data-eur="{{ $data['jan1PositionsValue']['EUR']['formatted'] }}"
                        data-usd="{{ $data['jan1PositionsValue']['USD']['formatted'] }}">
                        <span>{!! $totalPositionsValue !!}</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </td>
</tr>
@endif
@php
    $jan1CashValue = $selectedCurrency === 'EUR'
        ? $data['jan1CashValue']['EUR']['formatted']
        : $data['jan1CashValue']['USD']['formatted'];
@endphp
<tr class="table-light">
    <td style="padding-left: 2rem;"><small class="text-muted">Cash:</small></td>
    <td><small class="text-muted currency-value"
        data-eur="{{ $data['jan1CashValue']['EUR']['formatted'] }}"
        data-usd="{{ $data['jan1CashValue']['USD']['formatted'] }}">
        <span>{!! $jan1CashValue !!}</span>
    </small></td>
</tr>

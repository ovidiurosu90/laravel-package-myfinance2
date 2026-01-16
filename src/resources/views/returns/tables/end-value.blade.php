{{-- Dec 31 Value (or Today if current year) --}}
<tr>
    <td class="fw-bold">
        @if($selectedYear == date('Y'))
            Portfolio Value at Today ({{ date('M d, Y') }})
        @else
            {{ trans('myfinance2::returns.labels.dec31-value') }}
        @endif
    </td>
    @php
        $dec31Value = $selectedCurrency === 'EUR'
            ? $data['dec31Value']['EUR']['formatted']
            : $data['dec31Value']['USD']['formatted'];
    @endphp
    <td class="currency-value"
        data-eur="{{ $data['dec31Value']['EUR']['formatted'] }}"
        data-usd="{{ $data['dec31Value']['USD']['formatted'] }}">
        <span>{!! $dec31Value !!}</span>
    </td>
</tr>
<tr class="table-light">
    <td style="padding-left: 2rem;">
        <small class="text-muted">
            Positions
            @if(count($data['dec31PositionDetails']) > 0)
                (
                <button class="btn btn-sm btn-link p-0"
                    data-bs-toggle="collapse"
                    data-bs-target="#dec31-positions-{{ $accountId }}"
                    style="padding: 0 !important; margin: 0 !important; text-decoration: none;">
                    {{ count($data['dec31PositionDetails']) }}
                </button>
                )
            @endif
        </small>
    </td>
    @php
        $dec31PositionsValue = $selectedCurrency === 'EUR'
            ? $data['dec31PositionsValue']['EUR']['formatted']
            : $data['dec31PositionsValue']['USD']['formatted'];
    @endphp
    <td><small class="text-muted currency-value"
        data-eur="{{ $data['dec31PositionsValue']['EUR']['formatted'] }}"
        data-usd="{{ $data['dec31PositionsValue']['USD']['formatted'] }}">
        <span>{!! $dec31PositionsValue !!}</span>
    </small></td>
</tr>
@if(count($data['dec31PositionDetails']) > 0)
<tr class="collapse" id="dec31-positions-{{ $accountId }}">
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
                @foreach($data['dec31PositionDetails'] as $position)
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
                        ? $data['dec31PositionsValue']['EUR']['formatted']
                        : $data['dec31PositionsValue']['USD']['formatted'];
                @endphp
                <tr class="table-light fw-bold small">
                    <td colspan="4"></td>
                    <td class="text-end">Total Positions:</td>
                    <td class="text-end currency-value"
                        data-eur="{{ $data['dec31PositionsValue']['EUR']['formatted'] }}"
                        data-usd="{{ $data['dec31PositionsValue']['USD']['formatted'] }}">
                        <span>{!! $totalPositionsValue !!}</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </td>
</tr>
@endif
@php
    $dec31CashValue = $selectedCurrency === 'EUR'
        ? $data['dec31CashValue']['EUR']['formatted']
        : $data['dec31CashValue']['USD']['formatted'];
@endphp
<tr class="table-light">
    <td style="padding-left: 2rem;"><small class="text-muted">Cash:</small></td>
    <td><small class="text-muted currency-value"
        data-eur="{{ $data['dec31CashValue']['EUR']['formatted'] }}"
        data-usd="{{ $data['dec31CashValue']['USD']['formatted'] }}">
        <span>{!! $dec31CashValue !!}</span>
    </small></td>
</tr>

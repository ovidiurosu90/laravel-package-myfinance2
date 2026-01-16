{{-- Dividends --}}
<tr>
    <td class="fw-bold">
        {{ trans('myfinance2::returns.labels.gross-dividends') }}
        @if(count($data['dividends']['items']) > 0)
            <button class="btn btn-sm btn-link p-0"
                data-bs-toggle="collapse"
                data-bs-target="#dividends-{{ $accountId }}">
                ({{ count($data['dividends']['items']) }} transactions)
            </button>
        @endif
    </td>
    <td class="currency-value"
        data-eur="{{ $data['dividends']['totals']['EUR']['formatted'] }}"
        data-usd="{{ $data['dividends']['totals']['USD']['formatted'] }}"
        @if(isset($data['dividends']['totals']['EUR']['calculatedFormatted']))
            data-eur-override="{{ $data['dividends']['totals']['EUR']['formatted'] }}"
            data-eur-calculated="{{ $data['dividends']['totals']['EUR']['calculatedFormatted'] }}"
        @endif
        @if(isset($data['dividends']['totals']['USD']['calculatedFormatted']))
            data-usd-override="{{ $data['dividends']['totals']['USD']['formatted'] }}"
            data-usd-calculated="{{ $data['dividends']['totals']['USD']['calculatedFormatted'] }}"
        @endif>
        @php
            $value = $selectedCurrency === 'EUR'
                ? $data['dividends']['totals']['EUR']['formatted']
                : $data['dividends']['totals']['USD']['formatted'];
        @endphp
        <span data-bs-toggle="tooltip" data-bs-placement="top">
            {!! $value !!}
        </span>
        @if($selectedCurrency === 'EUR' && isset($data['dividends']['totals']['EUR']['calculatedFormatted']))
            <i class="fa-solid fa-circle-info ms-1 dividends-override-icon"
                style="font-size: 0.75rem; color: black;"
                data-bs-toggle="tooltip"
                data-bs-placement="top"
                data-bs-title="This value has been overridden to match the annual statement."></i>
            <small style="color: #6c757d; margin-left: 0.5rem;" class="dividends-calculated-value">
                (Calculated: {!! $data['dividends']['totals']['EUR']['calculatedFormatted'] !!})
            </small>
        @elseif($selectedCurrency === 'USD' && isset($data['dividends']['totals']['USD']['calculatedFormatted']))
            <i class="fa-solid fa-circle-info ms-1 dividends-override-icon"
                style="font-size: 0.75rem; color: black;"
                data-bs-toggle="tooltip"
                data-bs-placement="top"
                data-bs-title="This value has been overridden to match the annual statement."></i>
            <small style="color: #6c757d; margin-left: 0.5rem;" class="dividends-calculated-value">
                (Calculated: {!! $data['dividends']['totals']['USD']['calculatedFormatted'] !!})
            </small>
        @endif
    </td>
</tr>
@if(count($data['dividends']['items']) > 0)
<tr class="collapse" id="dividends-{{ $accountId }}">
    <td colspan="2">
        <table class="table table-sm table-striped mb-0">
            <thead>
                <tr class="small">
                    <th>Date</th>
                    <th>Symbol</th>
                    <th class="text-end">Gross Amount in EUR</th>
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
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['dividends']['items'] as $dividend)
                    <tr class="small">
                        <td class="text-nowrap">{{ $dividend['date'] }}</td>
                        <td class="text-nowrap">{{ $dividend['symbol'] }}</td>
                        @php
                            $tooltipEUR = "EURUSD: {$dividend['EUR']['eurusdRate']}<br>"
                                . "{$dividend['EUR']['conversionPair']}: "
                                . "{$dividend['EUR']['conversionExchangeRateClean']}";
                            $tooltipUSD = "EURUSD: {$dividend['USD']['eurusdRate']}<br>"
                                . "{$dividend['USD']['conversionPair']}: "
                                . "{$dividend['USD']['conversionExchangeRateClean']}";
                        @endphp
                        <td class="text-end text-nowrap currency-value"
                            data-eur="{{ $dividend['EUR']['formatted'] }}"
                            data-usd="{{ $dividend['USD']['formatted'] }}"
                            data-eur-value="{{ $dividend['EUR']['amount'] }}"
                            data-usd-value="{{ $dividend['USD']['amount'] }}"
                            data-eur-tooltip="{{ $tooltipEUR }}"
                            data-usd-tooltip="{{ $tooltipUSD }}"
                            data-eur-show-warning="{{ $dividend['EUR']['showMissingRateWarning'] ? 'true' : 'false' }}"
                            data-usd-show-warning="{{ $dividend['USD']['showMissingRateWarning'] ? 'true' : 'false' }}">
                            <span @if($dividend['EUR']['conversionExchangeRateClean']) data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-custom-class="big-tooltips"
                                data-bs-html="true"
                                data-bs-title="{{ $tooltipEUR }}" @endif>
                            {!! $dividend['EUR']['formatted'] !!}</span>
                            @php
                                $showWarning = $dividend['EUR']['showMissingRateWarning']
                                    ? 'inline'
                                    : 'none';
                            @endphp
                            <i class="fa-solid fa-circle-info ms-1 dividend-warning-icon"
                                style="font-size: 0.75rem; color: black; display: {{ $showWarning }};"
                                data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-title="Exchange rate was fetched from API (not stored in dividend entry)"></i>
                        </td>
                        <td class="text-end text-nowrap">
                            @if($dividend['fee'] > 0)
                                <small class="text-danger">
                                    {!! $dividend['feeFormatted'] !!}
                                </small>
                            @endif
                        </td>
                        <td>
                            @if($dividend['description'])
                                <em>{{ $dividend['description'] }}</em>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @php
                    $totalEUR = $data['dividends']['totals']['EUR']['calculatedFormatted']
                        ?? $data['dividends']['totals']['EUR']['formatted'];
                    $totalUSD = $data['dividends']['totals']['USD']['calculatedFormatted']
                        ?? $data['dividends']['totals']['USD']['formatted'];
                    $totalValue = $selectedCurrency === 'EUR' ? $totalEUR : $totalUSD;
                @endphp
                <tr class="small fw-bold">
                    <td>Total Gross Dividends:</td>
                    <td></td>
                    <td class="text-end currency-value"
                        data-eur="{{ $totalEUR }}"
                        data-usd="{{ $totalUSD }}">
                        <span>{!! $totalValue !!}</span>
                    </td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        {{-- Dividends Summary by Dividend Currency --}}
        @if(!empty($data['dividendsSummaryByTransactionCurrency']))
        <div style="margin-top: 1.5rem;">
            <strong style="font-size: 0.95rem;">
                Dividends Summary by Dividend Currency:
            </strong>
            <table class="table table-sm table-striped mb-0" style="margin-top: 0.75rem;">
                <thead>
                    <tr class="small">
                        <th style="width: 15%;">Dividend Currency</th>
                        <th class="text-end" style="width: 42.5%;">Gross Amount</th>
                        <th class="text-end" style="width: 42.5%;">Fee / Tax</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['dividendsSummaryByTransactionCurrency']['groups'] as $summary)
                    <tr class="small">
                        <td class="fw-bold">{{ $summary['isoCode'] }} {!! $summary['currencyCode'] !!}</td>
                        <td class="text-end text-nowrap">
                            {!! $summary['totalGrossFormatted'] !!}
                        </td>
                        <td class="text-end text-nowrap text-danger">
                            @if($summary['totalFee'] > 0)
                                {!! $summary['totalFeeFormatted'] !!}
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Note about remapped symbols --}}
            @if(!empty($data['dividendsSummaryByTransactionCurrency']['remapped']))
            <div style="margin-top: 1rem; padding: 0.75rem; background-color: #f8f9fa; border-left: 3px solid #17a2b8;">
                <small style="color: #495057;">
                    <strong>Note:</strong> The following symbols have been remapped for
                    tax reporting purposes:
                    <br>
                    @foreach($data['dividendsSummaryByTransactionCurrency']['remapped'] as $remapped)
                        • <strong>{{ $remapped['symbol'] }}</strong>
                        ({{ $remapped['originalCurrency'] }} →
                        {{ $remapped['taxCurrency'] }}):
                        {!! $remapped['totalGrossFormatted'] !!}<br>
                    @endforeach
                </small>
            </div>
            @endif
        </div>
        @endif
    </td>
</tr>
@endif

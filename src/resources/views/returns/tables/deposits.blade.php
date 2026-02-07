{{-- Deposits --}}
@php
    $hasDepositFees = ($data['deposits']['totals']['EUR']['fees'] ?? 0) > 0
        || ($data['deposits']['totals']['USD']['fees'] ?? 0) > 0;
@endphp
<tr>
    <td class="fw-bold">
        {{ trans('myfinance2::returns.labels.deposits') }}
        @if(count($data['deposits']['items']) > 0)
            <button class="btn btn-sm btn-link p-0"
                data-bs-toggle="collapse"
                data-bs-target="#deposits-{{ $accountId }}">
                ({{ count($data['deposits']['items']) }} transactions)
            </button>
        @endif
    </td>
    @php
        // Use adjusted totals (amount - fees) when fees exist and no override
        if ($selectedCurrency === 'EUR' && isset($data['totalDepositsOverride']['EUR'])) {
            $totalValue = $data['totalDepositsOverride']['EUR']['overrideFormatted'];
        } elseif ($selectedCurrency === 'USD' && isset($data['totalDepositsOverride']['USD'])) {
            $totalValue = $data['totalDepositsOverride']['USD']['overrideFormatted'];
        } else {
            $totalValue = $selectedCurrency === 'EUR'
                ? ($hasDepositFees
                    ? $data['deposits']['totals']['EUR']['adjustedFormatted']
                    : $data['deposits']['totals']['EUR']['formatted'])
                : ($hasDepositFees
                    ? $data['deposits']['totals']['USD']['adjustedFormatted']
                    : $data['deposits']['totals']['USD']['formatted']);
        }
    @endphp
    @php
        $dataEur = isset($data['totalDepositsOverride']['EUR'])
            ? $data['totalDepositsOverride']['EUR']['overrideFormatted']
            : ($hasDepositFees
                ? $data['deposits']['totals']['EUR']['adjustedFormatted']
                : $data['deposits']['totals']['EUR']['formatted']);
        $dataUsd = isset($data['totalDepositsOverride']['USD'])
            ? $data['totalDepositsOverride']['USD']['overrideFormatted']
            : ($hasDepositFees
                ? $data['deposits']['totals']['USD']['adjustedFormatted']
                : $data['deposits']['totals']['USD']['formatted']);
    @endphp
    <td class="currency-value"
        data-eur="{{ $dataEur }}"
        data-usd="{{ $dataUsd }}"
        data-eur-fees-text="{{ $data['deposits']['totals']['EUR']['feesText'] ?? '' }}"
        data-usd-fees-text="{{ $data['deposits']['totals']['USD']['feesText'] ?? '' }}"
        @if(isset($data['totalDepositsOverride']['EUR']))
            data-eur-override="{{ $data['totalDepositsOverride']['EUR']['overrideFormatted'] }}"
            data-eur-calculated="{{ $data['totalDepositsOverride']['EUR']['calculatedFormatted'] }}"
        @endif
        @if(isset($data['totalDepositsOverride']['USD']))
            data-usd-override="{{ $data['totalDepositsOverride']['USD']['overrideFormatted'] }}"
            data-usd-calculated="{{ $data['totalDepositsOverride']['USD']['calculatedFormatted'] }}"
        @endif
        @if(isset($data['totalDepositsOverride']['reason']))
            data-override-reason="{{ $data['totalDepositsOverride']['reason'] }}"
        @endif>
        <div>
            <span>{!! $totalValue !!}</span>
            @if(isset($data['totalDepositsOverride']['EUR'])
                || isset($data['totalDepositsOverride']['USD']))
                @php
                    $showOverride = ($selectedCurrency === 'EUR'
                            && isset($data['totalDepositsOverride']['EUR']))
                        || ($selectedCurrency === 'USD'
                            && isset($data['totalDepositsOverride']['USD']));
                    $calculatedText = $selectedCurrency === 'EUR'
                        ? ($data['totalDepositsOverride']['EUR']['calculatedFormatted'] ?? '')
                        : ($data['totalDepositsOverride']['USD']['calculatedFormatted'] ?? '');
                @endphp
                <i class="fa-solid fa-circle-info ms-1 deposits-override-icon"
                    style="font-size: 0.75rem; color: black;
                        {{ $showOverride ? '' : 'display: none;' }}"
                    data-bs-toggle="tooltip"
                    data-bs-title="This deposit total has been overridden.
                        {{ $data['totalDepositsOverride']['reason']
                            ?? 'See configuration for details.' }}"></i>
                <small style="color: #6c757d; margin-left: 0.5rem;
                    {{ $showOverride ? '' : 'display: none;' }}"
                    class="deposits-calculated-value">
                    (Calculated: {!! $calculatedText !!})
                </small>
            @endif
            @if($hasDepositFees)
                @php
                    $currentFeesText = $selectedCurrency === 'EUR'
                        ? ($data['deposits']['totals']['EUR']['feesText'] ?? '')
                        : ($data['deposits']['totals']['USD']['feesText'] ?? '');
                @endphp
                <span style="font-size: 0.85rem; color: #6c757d; margin-left: 0.25rem;"
                    class="transaction-fees-text">
                    {!! $currentFeesText !!}
                </span>
            @endif
        </div>
    </td>
</tr>
@if(count($data['deposits']['items']) > 0)
<tr class="collapse" id="deposits-{{ $accountId }}">
    <td colspan="2">
        <table class="table table-sm table-striped mb-0">
            <thead>
                <tr class="small">
                    <th>Date</th>
                    <th class="text-end">Amount in {{ $selectedCurrency }}</th>
                    <th class="text-end">
                        Fee in {{ $selectedCurrency }}
                        @php
                            $feeConvertedInfo = 'Fee converted to display currency'
                                . ' using the same exchange rate as Amount.';
                        @endphp
                        <i class="fa fa-info-circle fa-fw"
                            style="margin-left: 0.25rem;"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            data-bs-title="{{ $feeConvertedInfo }}"></i>
                    </th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['deposits']['items'] as $deposit)
                    @php
                        $tooltipEUR = "EURUSD: {$deposit['EUR']['eurusdRate']}<br>"
                            . "{$deposit['EUR']['conversionPair']}: "
                            . "{$deposit['EUR']['conversionExchangeRateClean']}";
                        $tooltipUSD = "EURUSD: {$deposit['USD']['eurusdRate']}<br>"
                            . "{$deposit['USD']['conversionPair']}: "
                            . "{$deposit['USD']['conversionExchangeRateClean']}";
                        $currentTooltip = $selectedCurrency === 'EUR'
                            ? $tooltipEUR : $tooltipUSD;
                        $currentValue = $selectedCurrency === 'EUR'
                            ? $deposit['EUR']['formatted']
                            : $deposit['USD']['formatted'];
                        $currentFeeValue = $selectedCurrency === 'EUR'
                            ? $deposit['EUR']['feeFormatted']
                            : $deposit['USD']['feeFormatted'];
                    @endphp
                    <tr class="small">
                        <td class="text-nowrap">{{ $deposit['date'] }}</td>
                        <td class="text-end text-nowrap currency-value"
                            data-eur="{{ $deposit['EUR']['formatted'] }}"
                            data-usd="{{ $deposit['USD']['formatted'] }}"
                            data-eur-tooltip="{{ $tooltipEUR }}"
                            data-usd-tooltip="{{ $tooltipUSD }}">
                            <span data-bs-toggle="tooltip"
                                data-bs-placement="top"
                                data-bs-custom-class="big-tooltips"
                                data-bs-html="true"
                                data-bs-title="{{ $currentTooltip }}">
                                {!! $currentValue !!}
                            </span>
                        </td>
                        <td class="text-end text-nowrap currency-value"
                            data-eur="{{ $deposit['EUR']['feeFormatted'] }}"
                            data-usd="{{ $deposit['USD']['feeFormatted'] }}"
                            data-eur-tooltip="{{ $tooltipEUR }}"
                            data-usd-tooltip="{{ $tooltipUSD }}">
                            @if(($deposit['EUR']['fee'] ?? 0) > 0
                                || ($deposit['USD']['fee'] ?? 0) > 0)
                                <span data-bs-toggle="tooltip"
                                    data-bs-placement="top"
                                    data-bs-custom-class="big-tooltips"
                                    data-bs-html="true"
                                    data-bs-title="{{ $currentTooltip }}">
                                <small>
                                    {!! $currentFeeValue !!}
                                </small></span>
                            @endif
                        </td>
                        <td>
                            @if($deposit['description'])
                                <em>{{ $deposit['description'] }}</em>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @php
                    $subtotalValue = $selectedCurrency === 'EUR'
                        ? $data['deposits']['totals']['EUR']['formatted']
                        : $data['deposits']['totals']['USD']['formatted'];
                @endphp
                <tr class="small fw-bold">
                    <td>Total Deposits:</td>
                    <td class="text-end currency-value"
                        data-eur="{{ $data['deposits']['totals']['EUR']['formatted'] }}"
                        data-usd="{{ $data['deposits']['totals']['USD']['formatted'] }}">
                        <span>{!! $subtotalValue !!}</span>
                    </td>
                    <td class="text-end currency-value"
                        data-eur="{{ $data['deposits']['totals']['EUR']['feesFormatted'] }}"
                        data-usd="{{ $data['deposits']['totals']['USD']['feesFormatted'] }}">
                        @if($hasDepositFees)
                            @php
                                $feeSubtotalValue = $selectedCurrency === 'EUR'
                                    ? $data['deposits']['totals']['EUR']['feesFormatted']
                                    : $data['deposits']['totals']['USD']['feesFormatted'];
                            @endphp
                            <span>{!! $feeSubtotalValue !!}</span>
                        @endif
                    </td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </td>
</tr>
@endif

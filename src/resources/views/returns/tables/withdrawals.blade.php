{{-- Withdrawals --}}
@php
    $hasWithdrawalFees = ($data['withdrawals']['totals']['EUR']['fees'] ?? 0) > 0
        || ($data['withdrawals']['totals']['USD']['fees'] ?? 0) > 0;
@endphp
<tr>
    <td class="fw-bold">
        {{ trans('myfinance2::returns.labels.withdrawals') }}
        @if(count($data['withdrawals']['items']) > 0)
            <button class="btn btn-sm btn-link p-0"
                data-bs-toggle="collapse"
                data-bs-target="#withdrawals-{{ $accountId }}">
                ({{ count($data['withdrawals']['items']) }} transactions)
            </button>
        @endif
    </td>
    @php
        // Use adjusted totals (amount + fees) when fees exist and no override
        if ($selectedCurrency === 'EUR'
            && isset($data['totalWithdrawalsOverride']['EUR'])) {
            $totalValue = $data['totalWithdrawalsOverride']['EUR']['overrideFormatted'];
        } elseif ($selectedCurrency === 'USD'
            && isset($data['totalWithdrawalsOverride']['USD'])) {
            $totalValue = $data['totalWithdrawalsOverride']['USD']['overrideFormatted'];
        } else {
            $totalValue = $selectedCurrency === 'EUR'
                ? ($hasWithdrawalFees
                    ? $data['withdrawals']['totals']['EUR']['adjustedFormatted']
                    : $data['withdrawals']['totals']['EUR']['formatted'])
                : ($hasWithdrawalFees
                    ? $data['withdrawals']['totals']['USD']['adjustedFormatted']
                    : $data['withdrawals']['totals']['USD']['formatted']);
        }
    @endphp
    @php
        $dataEur = isset($data['totalWithdrawalsOverride']['EUR'])
            ? $data['totalWithdrawalsOverride']['EUR']['overrideFormatted']
            : ($hasWithdrawalFees
                ? $data['withdrawals']['totals']['EUR']['adjustedFormatted']
                : $data['withdrawals']['totals']['EUR']['formatted']);
        $dataUsd = isset($data['totalWithdrawalsOverride']['USD'])
            ? $data['totalWithdrawalsOverride']['USD']['overrideFormatted']
            : ($hasWithdrawalFees
                ? $data['withdrawals']['totals']['USD']['adjustedFormatted']
                : $data['withdrawals']['totals']['USD']['formatted']);
    @endphp
    <td class="currency-value"
        data-eur="{{ $dataEur }}"
        data-usd="{{ $dataUsd }}"
        data-eur-fees-text="{{ $data['withdrawals']['totals']['EUR']['feesText'] ?? '' }}"
        data-usd-fees-text="{{ $data['withdrawals']['totals']['USD']['feesText'] ?? '' }}"
        @if(isset($data['totalWithdrawalsOverride']['EUR']))
            data-eur-override="{{ $data['totalWithdrawalsOverride']['EUR']['overrideFormatted'] }}"
            data-eur-calculated="{{ $data['totalWithdrawalsOverride']['EUR']['calculatedFormatted'] }}"
        @endif
        @if(isset($data['totalWithdrawalsOverride']['USD']))
            data-usd-override="{{ $data['totalWithdrawalsOverride']['USD']['overrideFormatted'] }}"
            data-usd-calculated="{{ $data['totalWithdrawalsOverride']['USD']['calculatedFormatted'] }}"
        @endif
        @if(isset($data['totalWithdrawalsOverride']['reason']))
            data-override-reason="{{ $data['totalWithdrawalsOverride']['reason'] }}"
        @endif>
        <div>
            <span>{!! $totalValue !!}</span>
            @if(isset($data['totalWithdrawalsOverride']['EUR'])
                || isset($data['totalWithdrawalsOverride']['USD']))
                @php
                    $showOverride = ($selectedCurrency === 'EUR'
                            && isset($data['totalWithdrawalsOverride']['EUR']))
                        || ($selectedCurrency === 'USD'
                            && isset($data['totalWithdrawalsOverride']['USD']));
                    $calculatedText = $selectedCurrency === 'EUR'
                        ? ($data['totalWithdrawalsOverride']['EUR']['calculatedFormatted']
                            ?? '')
                        : ($data['totalWithdrawalsOverride']['USD']['calculatedFormatted']
                            ?? '');
                @endphp
                <i class="fa-solid fa-circle-info ms-1 withdrawals-override-icon"
                    style="font-size: 0.75rem; color: black;
                        {{ $showOverride ? '' : 'display: none;' }}"
                    data-bs-toggle="tooltip"
                    data-bs-title="This withdrawal total has been overridden.
                        {{ $data['totalWithdrawalsOverride']['reason']
                            ?? 'See configuration for details.' }}"></i>
                <small style="color: #6c757d; margin-left: 0.5rem;
                    {{ $showOverride ? '' : 'display: none;' }}"
                    class="withdrawals-calculated-value">
                    (Calculated: {!! $calculatedText !!})
                </small>
            @endif
            @if($hasWithdrawalFees)
                @php
                    $currentFeesText = $selectedCurrency === 'EUR'
                        ? ($data['withdrawals']['totals']['EUR']['feesText'] ?? '')
                        : ($data['withdrawals']['totals']['USD']['feesText'] ?? '');
                @endphp
                <span style="font-size: 0.85rem; color: #6c757d; margin-left: 0.25rem;"
                    class="transaction-fees-text">
                    {!! $currentFeesText !!}
                </span>
            @endif
        </div>
    </td>
</tr>
@if(count($data['withdrawals']['items']) > 0)
<tr class="collapse" id="withdrawals-{{ $accountId }}">
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
                @foreach($data['withdrawals']['items'] as $withdrawal)
                    @php
                        $tooltipEUR = "EURUSD: {$withdrawal['EUR']['eurusdRate']}<br>"
                            . "{$withdrawal['EUR']['conversionPair']}: "
                            . "{$withdrawal['EUR']['conversionExchangeRateClean']}";
                        $tooltipUSD = "EURUSD: {$withdrawal['USD']['eurusdRate']}<br>"
                            . "{$withdrawal['USD']['conversionPair']}: "
                            . "{$withdrawal['USD']['conversionExchangeRateClean']}";
                        $currentTooltip = $selectedCurrency === 'EUR'
                            ? $tooltipEUR : $tooltipUSD;
                        $currentValue = $selectedCurrency === 'EUR'
                            ? $withdrawal['EUR']['formatted']
                            : $withdrawal['USD']['formatted'];
                        $currentFeeValue = $selectedCurrency === 'EUR'
                            ? $withdrawal['EUR']['feeFormatted']
                            : $withdrawal['USD']['feeFormatted'];
                    @endphp
                    <tr class="small">
                        <td class="text-nowrap">{{ $withdrawal['date'] }}</td>
                        <td class="text-end text-nowrap currency-value"
                            data-eur="{{ $withdrawal['EUR']['formatted'] }}"
                            data-usd="{{ $withdrawal['USD']['formatted'] }}"
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
                            data-eur="{{ $withdrawal['EUR']['feeFormatted'] }}"
                            data-usd="{{ $withdrawal['USD']['feeFormatted'] }}"
                            data-eur-tooltip="{{ $tooltipEUR }}"
                            data-usd-tooltip="{{ $tooltipUSD }}">
                            @if(($withdrawal['EUR']['fee'] ?? 0) > 0
                                || ($withdrawal['USD']['fee'] ?? 0) > 0)
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
                            @if($withdrawal['isTransfer'] ?? false)
                                <i class="fa-solid fa-shuffle text-muted me-1"
                                    data-bs-toggle="tooltip"
                                    title="In-kind transfer"></i>
                            @endif
                            @if($withdrawal['description'])
                                <em>{{ $withdrawal['description'] }}</em>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @php
                    $subtotalValue = $selectedCurrency === 'EUR'
                        ? $data['withdrawals']['totals']['EUR']['formatted']
                        : $data['withdrawals']['totals']['USD']['formatted'];
                @endphp
                <tr class="small fw-bold">
                    <td>Total Withdrawals:</td>
                    <td class="text-end currency-value"
                        data-eur="{{ $data['withdrawals']['totals']['EUR']['formatted'] }}"
                        data-usd="{{ $data['withdrawals']['totals']['USD']['formatted'] }}">
                        <span>{!! $subtotalValue !!}</span>
                    </td>
                    <td class="text-end currency-value"
                        data-eur="{{ $data['withdrawals']['totals']['EUR']['feesFormatted'] }}"
                        data-usd="{{ $data['withdrawals']['totals']['USD']['feesFormatted'] }}">
                        @if($hasWithdrawalFees)
                            @php
                                $feeSubtotalValue = $selectedCurrency === 'EUR'
                                    ? $data['withdrawals']['totals']['EUR']['feesFormatted']
                                    : $data['withdrawals']['totals']['USD']['feesFormatted'];
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

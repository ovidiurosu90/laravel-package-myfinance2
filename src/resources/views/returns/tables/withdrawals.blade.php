{{-- Withdrawals --}}
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
        // Check for override and use it if available, otherwise use regular totals
        if ($selectedCurrency === 'EUR' && isset($data['totalWithdrawalsOverride']['EUR'])) {
            $totalValue = $data['totalWithdrawalsOverride']['EUR']['overrideFormatted'];
        } elseif ($selectedCurrency === 'USD' && isset($data['totalWithdrawalsOverride']['USD'])) {
            $totalValue = $data['totalWithdrawalsOverride']['USD']['overrideFormatted'];
        } else {
            $totalValue = $selectedCurrency === 'EUR'
                ? $data['withdrawals']['totals']['EUR']['formatted']
                : $data['withdrawals']['totals']['USD']['formatted'];
        }
    @endphp
    @php
        $dataEur = isset($data['totalWithdrawalsOverride']['EUR'])
            ? $data['totalWithdrawalsOverride']['EUR']['overrideFormatted']
            : $data['withdrawals']['totals']['EUR']['formatted'];
        $dataUsd = isset($data['totalWithdrawalsOverride']['USD'])
            ? $data['totalWithdrawalsOverride']['USD']['overrideFormatted']
            : $data['withdrawals']['totals']['USD']['formatted'];
    @endphp
    <td class="currency-value"
        data-eur="{{ $dataEur }}"
        data-usd="{{ $dataUsd }}"
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
        <span>{!! $totalValue !!}</span>
        @if(isset($data['totalWithdrawalsOverride']['EUR']) || isset($data['totalWithdrawalsOverride']['USD']))
            @php
                $showOverride = ($selectedCurrency === 'EUR'
                        && isset($data['totalWithdrawalsOverride']['EUR']))
                    || ($selectedCurrency === 'USD'
                        && isset($data['totalWithdrawalsOverride']['USD']));
                $calculatedText = $selectedCurrency === 'EUR'
                    ? ($data['totalWithdrawalsOverride']['EUR']['calculatedFormatted'] ?? '')
                    : ($data['totalWithdrawalsOverride']['USD']['calculatedFormatted'] ?? '');
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
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['withdrawals']['items'] as $withdrawal)
                    @php
                        $tooltipEUR = "EURUSD: {$withdrawal['EUR']['eurusdRate']}<br>" .
                            "{$withdrawal['EUR']['conversionPair']}: " .
                            "{$withdrawal['EUR']['conversionExchangeRateClean']}";
                        $tooltipUSD = "EURUSD: {$withdrawal['USD']['eurusdRate']}<br>" .
                            "{$withdrawal['USD']['conversionPair']}: " .
                            "{$withdrawal['USD']['conversionExchangeRateClean']}";
                        $currentTooltip = $selectedCurrency === 'EUR' ? $tooltipEUR : $tooltipUSD;
                        $currentValue = $selectedCurrency === 'EUR'
                            ? $withdrawal['EUR']['formatted']
                            : $withdrawal['USD']['formatted'];
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
                        <td>
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
                    <td></td>
                </tr>
            </tbody>
        </table>
    </td>
</tr>
@endif

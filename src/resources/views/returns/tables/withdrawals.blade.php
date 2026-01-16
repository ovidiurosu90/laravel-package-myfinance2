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
        $totalValue = $selectedCurrency === 'EUR'
            ? $data['withdrawals']['totals']['EUR']['formatted']
            : $data['withdrawals']['totals']['USD']['formatted'];
    @endphp
    <td class="currency-value"
        data-eur="{{ $data['withdrawals']['totals']['EUR']['formatted'] }}"
        data-usd="{{ $data['withdrawals']['totals']['USD']['formatted'] }}">
        <span>{!! $totalValue !!}</span>
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

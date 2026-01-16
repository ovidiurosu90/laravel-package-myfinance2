{{-- Deposits --}}
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
        $totalValue = $selectedCurrency === 'EUR'
            ? $data['deposits']['totals']['EUR']['formatted']
            : $data['deposits']['totals']['USD']['formatted'];
    @endphp
    <td class="currency-value"
        data-eur="{{ $data['deposits']['totals']['EUR']['formatted'] }}"
        data-usd="{{ $data['deposits']['totals']['USD']['formatted'] }}">
        <span>{!! $totalValue !!}</span>
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
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['deposits']['items'] as $deposit)
                    @php
                        $tooltipEUR = "EURUSD: {$deposit['EUR']['eurusdRate']}<br>" .
                            "{$deposit['EUR']['conversionPair']}: {$deposit['EUR']['conversionExchangeRateClean']}";
                        $tooltipUSD = "EURUSD: {$deposit['USD']['eurusdRate']}<br>" .
                            "{$deposit['USD']['conversionPair']}: {$deposit['USD']['conversionExchangeRateClean']}";
                        $currentTooltip = $selectedCurrency === 'EUR' ? $tooltipEUR : $tooltipUSD;
                        $currentValue = $selectedCurrency === 'EUR'
                            ? $deposit['EUR']['formatted']
                            : $deposit['USD']['formatted'];
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
                    <td></td>
                </tr>
            </tbody>
        </table>
    </td>
</tr>
@endif

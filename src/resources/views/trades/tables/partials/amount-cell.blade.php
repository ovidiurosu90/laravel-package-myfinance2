{{-- The trade list's Amount cell: quantity, unit price and principal amount in one column. The amount in the trade
     currency (and in the account currency when it differs), then "quantity × unit price" and the exchange rate
     beneath, muted. Sorts by the amount in EUR, signed as cash (a BUY negative, a SELL positive).
     $trade: the Trade; $eurRates: LatestEurRates. --}}
@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
@php
    $tradeCode  = $trade->tradeCurrencyModel->display_code;
    $otherCcy   = $trade->accountModel->currency->iso_code != $trade->tradeCurrencyModel->iso_code;
    $fx         = $trade->getCleanExchangeRate();
    $amountEur  = $trade->signedAmountEur($eurRates);
@endphp
<td class="text-right text-nowrap" data-order="{{ $amountEur }}">
    <div data-bs-toggle="tooltip"
        title="Amount in trade currency; {{ MoneyFormat::get_formatted_gain_plain('€', $amountEur) }} at today's rate,
            what the column sorts by (a BUY spends, a SELL brings in)">
        {!! $trade->getFormattedPrincipleAmount() !!}
    </div>
    @if($otherCcy)
        <div data-bs-toggle="tooltip" title="Amount in account currency">
            {!! $trade->getFormattedPrincipleAmountInAccountCurrency() !!}
        </div>
    @endif
    <div class="text-muted small" data-bs-toggle="tooltip" title="Quantity × unit price">
        {{ $trade->getCleanQuantity() }} &times;
        {!! MoneyFormat::get_formatted_price_display($tradeCode, (float) $trade->unit_price, true) !!}
    </div>
    @if($fx != 1)
        <div class="text-muted small" data-bs-toggle="tooltip" title="Exchange rate">FX {{ $fx }}</div>
    @endif
</td>

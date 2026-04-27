<script type="module">
$(document).ready(function()
{
    var tradeCurrencies = {!! json_encode($tradeCurrencies) !!};
    var tradeCurrenciesByIsoCode = {};
    for (let i in tradeCurrencies) {
        tradeCurrenciesByIsoCode[tradeCurrencies[i]['iso_code']] = tradeCurrencies[i];
    }

    var symbolPrefill = @json($symbolPrefill ?? null);

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    var $symbolSelect     = $('#symbol-select');
    var $limitPriceInput  = $('#limit_price');
    var $quantityInput    = $('#quantity-input');
    var $descriptionInput = $('#description');
    var $suggestionBanner = $('#smart-prefill-suggestion');

    var applySmartPrefill = function(symbol)
    {
        var accountSelectize = $('#account-select')[0].selectize;
        var accountId = accountSelectize ? accountSelectize.getValue() : '';

        $.ajax({
            type: 'GET',
            url:  "{{ url('/get-finance-data') }}",
            data: { symbol: symbol, timestamp: null, account_id: accountId || null },
            success: function(data)
            {
                var s = data.suggestion;

                var actionSelectize = $('#action-select')[0].selectize;
                if (actionSelectize && !actionSelectize.getValue()) {
                    actionSelectize.setValue(s.action);
                }

                if (!$limitPriceInput.val()) {
                    $limitPriceInput.val(s.limit_price).trigger('input');
                }

                if (s.suggested_qty !== null && !$quantityInput.val()) {
                    $quantityInput.val(s.suggested_qty).trigger('input');
                }

                if (s.suggested_account_id && accountSelectize && !accountSelectize.getValue()) {
                    accountSelectize.setValue(s.suggested_account_id);
                }

                if (tradeCurrenciesByIsoCode[data.currency]) {
                    var tcSelectize = $('#trade_currency-select')[0].selectize;
                    if (tcSelectize && !tcSelectize.getValue()) {
                        tcSelectize.setValue(tradeCurrenciesByIsoCode[data.currency]['id']);
                    }
                }

                var $exchangeRateInput = $('#exchange_rate');
                if (s.exchange_rate && !$exchangeRateInput.val()) {
                    $exchangeRateInput.val(s.exchange_rate);
                }

                var alertClass = s.weak_signal
                    ? 'alert-warning'
                    : (s.action === 'BUY' ? 'alert-success' : 'alert-danger');
                $suggestionBanner
                    .removeClass('alert-info alert-success alert-danger alert-warning')
                    .addClass(alertClass);

                var reasonText;
                if (s.weak_signal) {
                    reasonText = 'no strong buy signal — only ' + s.pct_below_high
                        + '% below 52wk high, no open positions to sell';
                } else if (s.action === 'BUY') {
                    reasonText = '2.5% below current price, which is already '
                        + s.pct_below_high + '% below 52wk high';
                } else {
                    reasonText = '2.5% above current price, which is already '
                        + s.pct_above_low + '% above 52wk low';
                }

                if (!$descriptionInput.val()) {
                    $descriptionInput.val(reasonText);
                }

                var partialSellNote = '';
                if (s.is_partial_sell && s.suggested_qty !== null) {
                    var openQtyStr = parseFloat(s.open_quantity.toFixed(8)).toString();
                    partialSellNote = ' <span class="badge bg-warning text-dark ms-1">'
                        + 'partial — ' + s.suggested_qty + ' of ' + openQtyStr + '</span>';
                }

                var qtyPart   = s.suggested_qty !== null ? s.suggested_qty + 'x ' : '';
                var totalPart = '';
                if (s.suggested_qty !== null) {
                    var tradeTotal = s.suggested_qty * s.limit_price;
                    totalPart = ' &asymp; ' + tradeTotal.toFixed(2) + ' ' + data.currency;
                    if (s.account_currency && s.account_currency !== data.currency
                        && s.exchange_rate
                    ) {
                        var accountTotal = (tradeTotal / s.exchange_rate).toFixed(2);
                        totalPart += ' (~' + accountTotal + ' ' + s.account_currency + ')';
                    }
                }

                $suggestionBanner.html(
                    '<strong>' + s.action + '</strong>'
                    + ' ' + qtyPart + symbol
                    + ' @ ' + s.limit_price + ' ' + data.currency
                    + totalPart
                    + ' &mdash; <span class="badge bg-secondary me-1">reason</span>'
                    + '<em>' + reasonText + '</em>'
                    + partialSellNote
                );
                $suggestionBanner.show();
            },
        });
    };

    if (symbolPrefill) {
        var symbolSelectize = $symbolSelect[0].selectize;
        if (symbolSelectize) {
            if (!symbolSelectize.options[symbolPrefill]) {
                symbolSelectize.addOption({ value: symbolPrefill, text: symbolPrefill });
            }
            symbolSelectize.setValue(symbolPrefill);
        }
        applySmartPrefill(symbolPrefill);
    }

    $('#get-finance-data').on('click', function()
    {
        var symbolSelectize = $symbolSelect[0].selectize;
        var symbol = symbolSelectize ? symbolSelectize.getValue() : $symbolSelect.val();
        if (symbol) {
            applySmartPrefill(symbol);
        }
    });
});
</script>

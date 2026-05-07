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

    var $symbolInput      = $('#symbol-input');
    var $limitPriceInput  = $('#limit_price');
    var $quantityInput    = $('#quantity-input');
    var $descriptionInput = $('#description');
    var $orderBanner      = $('#order-summary-banner');
    var $fetchedSymbolName = $('#fetched-symbol-name');

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

                $fetchedSymbolName.find('span').html(data.name);
                $fetchedSymbolName.show();

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

                $orderBanner
                    .data('reason', reasonText)
                    .data('weak-signal', s.weak_signal ? 1 : 0);

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

                if (!$descriptionInput.val()) {
                    $descriptionInput.val(reasonText);
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
                    $exchangeRateInput.val(s.exchange_rate).trigger('input');
                }

                window.handleAvailableQuantity(data.available_quantity);
                $orderBanner.trigger('banner-update');
            },
        });
    };

    if (symbolPrefill) {
        $symbolInput.val(symbolPrefill).trigger('input');
        applySmartPrefill(symbolPrefill);
    }

    $('#get-finance-data').on('click', function()
    {
        var symbol = $symbolInput.val();
        if (symbol) {
            applySmartPrefill(symbol);
        }
    });
});
</script>

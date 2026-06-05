<script type="module">
$(document).ready(function()
{
    @include('myfinance2::general.scripts.partials.finance-utils')

    var $symbolInput        = $('#symbol-input');
    var $limitPriceInput    = $('#limit_price');
    var $fetchedLimitPrice  = $('#fetched-limit-price');
    var $quantityInput      = $('#quantity-input');
    var $descriptionInput   = $('#description');
    var $orderBanner        = $('#order-summary-banner');

    var fetchedSuggestion   = null;
    var lastAutoDescription = null;
    var fetchCounter        = 0;
    var urlParamsPrefilled  = $limitPriceInput.val() !== '';

    var applySmartPrefill = function(symbol)
    {
        var accountSelectize = $('#account-select')[0].selectize;
        var accountId = accountSelectize ? accountSelectize.getValue() : '';
        var myFetch = ++fetchCounter;

        $.ajax({
            type: 'GET',
            url:  "{{ url('/get-finance-data') }}",
            data: { symbol: symbol, timestamp: null, account_id: accountId || null },
            success: function(data)
            {
                if (myFetch !== fetchCounter) return;
                var s = data.suggestion;
                fetchedSuggestion = s;
                storeFetchedData(data);

                var actionSelectize = $('#action-select')[0].selectize;
                if (actionSelectize && !actionSelectize.getValue()) {
                    actionSelectize.setValue(s.action);
                }

                $fetchedLimitPrice.find('span')
                    .text(data.price)
                    .attr('data-bs-original-title', data.quote_timestamp)
                    .end().show();

                if (!urlParamsPrefilled) {
                    $limitPriceInput.val(s.limit_price);
                }

                if (s.suggested_qty !== null && !$quantityInput.val()) {
                    $quantityInput.val(s.suggested_qty);
                }

                setFetchedTradeCurrency(data);

                var $exchangeRateInput = $('#exchange_rate');
                if (s.exchange_rate && !$exchangeRateInput.val()) {
                    $exchangeRateInput.val(s.exchange_rate);
                }

                if (s.suggested_account_id && accountSelectize && !accountSelectize.getValue()) {
                    accountSelectize.setValue(s.suggested_account_id);
                }

                var currentLimitPrice = parseFloat($limitPriceInput.val());
                var noteText = (!isNaN(currentLimitPrice) && fetchedHigh && fetchedLow)
                    ? buildNoteText(currentLimitPrice, fetchedPrice,
                        fetchedHigh, fetchedLow, fetchedCurrencySymbol)
                    : null;
                var reasonText = (s.weak_signal && noteText)
                    ? 'weak signal; ' + noteText
                    : noteText;

                var prevAutoDescription = lastAutoDescription;
                lastAutoDescription = reasonText;

                $orderBanner
                    .data('reason', reasonText)
                    .data('weak-signal', s.weak_signal ? 1 : 0);

                if ((!$descriptionInput.val() || $descriptionInput.val() === prevAutoDescription)
                    && reasonText)
                {
                    $descriptionInput.val(reasonText);
                }

                window.handleAvailableQuantity(data.available_quantity);
                $orderBanner.trigger('banner-update');
            },
        });
    };

    $limitPriceInput.on('input', function()
    {
        if (!fetchedPrice || !fetchedHigh || !fetchedLow) return;

        var limitPrice = parseFloat($(this).val());
        if (isNaN(limitPrice) || limitPrice <= 0) return;

        var newText = buildNoteText(limitPrice, fetchedPrice,
            fetchedHigh, fetchedLow, fetchedCurrencySymbol);
        if (!newText) return;

        if (fetchedSuggestion && fetchedSuggestion.weak_signal) {
            newText = 'weak signal; ' + newText;
        }

        $orderBanner.data('reason', newText).trigger('banner-update');

        if ($descriptionInput.val() === lastAutoDescription) {
            $descriptionInput.val(newText);
            lastAutoDescription = newText;
        }
    });

    @include('myfinance2::general.scripts.partials.finance-symbol-triggers')
});
</script>

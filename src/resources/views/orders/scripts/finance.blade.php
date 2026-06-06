<script type="module">
$(document).ready(function()
{
    @include('myfinance2::general.scripts.partials.finance-utils')

    var $symbolInput         = $('#symbol-input');
    var $limitPriceInput     = $('#limit_price');
    var $fetchedLimitPrice   = $('#fetched-limit-price');
    var $fetchedDescription  = $('#fetched-description');
    var $quantityInput       = $('#quantity-input');
    var $descriptionInput    = $('#description');
    var $orderBanner         = $('#order-summary-banner');

    var fetchedSuggestion    = null;
    var lastAutoDescription  = null;
    var fetchCounter         = 0;
    var urlParamsPrefilled   = $limitPriceInput.val() !== '';

    // In label-only mode (edit) the regenerated description is surfaced next to
    // the Description label; it never overwrites the textarea or other inputs.
    var labelOnly            = {{ !empty($labelOnly) ? 'true' : 'false' }};

    // The rationale text for the current limit price: the price relationships
    // (vs current / 52W high / 52W low), prefixed with "weak signal" when the
    // fetched suggestion flagged one.
    var buildReasonText = function()
    {
        if (!fetchedPrice || !fetchedHigh || !fetchedLow) return null;

        var limitPrice = parseFloat($limitPriceInput.val());
        if (isNaN(limitPrice) || limitPrice <= 0) return null;

        var text = buildNoteText(limitPrice, fetchedPrice,
            fetchedHigh, fetchedLow, fetchedCurrencySymbol);
        if (!text) return null;

        if (fetchedSuggestion && fetchedSuggestion.weak_signal) {
            text = 'weak signal; ' + text;
        }
        return text;
    };

    var showFetchedDescription = function(text)
    {
        if (text === undefined) text = buildReasonText();
        if (!text) { $fetchedDescription.hide(); return; }

        $fetchedDescription.find('span').text(text).end().show();
    };

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

                $fetchedLimitPrice.find('span')
                    .text(data.price)
                    .attr('data-bs-original-title', data.quote_timestamp)
                    .end().show();

                // Edit: the limit price is already set, so the rationale can be
                // computed now and surfaced in the label without mutating inputs.
                if (labelOnly) {
                    var labelReason = buildReasonText();
                    $orderBanner
                        .data('reason', labelReason)
                        .data('weak-signal', s.weak_signal ? 1 : 0);
                    // Trade currency tracks the symbol, so keep the select in sync.
                    setFetchedTradeCurrency(data);
                    showFetchedDescription(labelReason);
                    window.handleAvailableQuantity(data.available_quantity);
                    $orderBanner.trigger('banner-update');
                    return;
                }

                var actionSelectize = $('#action-select')[0].selectize;
                if (actionSelectize && !actionSelectize.getValue()) {
                    actionSelectize.setValue(s.action);
                }

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

                // Compute the rationale after the limit price has been prefilled.
                var reasonText = buildReasonText();

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
        var newText = buildReasonText();

        if (labelOnly) {
            showFetchedDescription(newText);
            if (newText) {
                $orderBanner.data('reason', newText).trigger('banner-update');
            }
            return;
        }

        if (!newText) return;

        $orderBanner.data('reason', newText).trigger('banner-update');

        if ($descriptionInput.val() === lastAutoDescription) {
            $descriptionInput.val(newText);
            lastAutoDescription = newText;
        }
    });

    @include('myfinance2::general.scripts.partials.finance-symbol-triggers')
});
</script>

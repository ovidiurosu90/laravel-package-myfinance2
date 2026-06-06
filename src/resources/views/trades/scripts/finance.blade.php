<script type="module">
$(document).ready(function()
{
    @include('myfinance2::general.scripts.partials.finance-utils')

    var $symbolInput          = $('#symbol-input');
    var $getFinanceData       = $('#get-finance-data');
    var $isListedButton       = $('#is-listed');
    var $timestampPickerInput = $('#timestamp-picker>input');
    var $accountSelect        = $('#account-select');
    var $editTradeForm        = $('#edit-trade-form');
    var $fetchedTradeCurrency = $('#fetched-trade-currency');
    var $fetchedUnitPrice     = $('#fetched-unit-price');
    var $fetchedDescription   = $('#fetched-description');
    var $unitPriceInput       = $('#unit_price');
    var $descriptionInput     = $('#description');

    var fetchedSuggestion   = null;
    var lastAutoDescription = null;
    var fetchCounter        = 0;
    var urlParamsPrefilled  = $unitPriceInput.val() !== '';

    // In label-only mode (edit) the regenerated description is surfaced next to
    // the Description label; it never overwrites the textarea or other inputs.
    var labelOnly           = {{ !empty($labelOnly) ? 'true' : 'false' }};

    // The auto-description for the current unit price: the price relationships
    // (vs current / 52W high / 52W low), prefixed with "weak signal" when the
    // fetched suggestion flagged one.
    var buildReasonText = function()
    {
        if (!fetchedPrice || !fetchedHigh || !fetchedLow) return null;

        var unitPrice = parseFloat($unitPriceInput.val());
        if (isNaN(unitPrice) || unitPrice <= 0) return null;

        var text = buildNoteText(unitPrice, fetchedPrice,
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

    var applyAutoDescription = function(unitPrice)
    {
        if (isNaN(unitPrice) || !fetchedHigh || !fetchedLow) return;

        var noteText = buildNoteText(unitPrice, fetchedPrice,
            fetchedHigh, fetchedLow, fetchedCurrencySymbol);
        if (!noteText) return;

        if (fetchedSuggestion && fetchedSuggestion.weak_signal) {
            noteText = 'weak signal; ' + noteText;
        }
        $descriptionInput.val(noteText);
        lastAutoDescription = noteText;
    };

    $isListedButton.click(function()
    {
        var unlisted = '{{ config('trades.unlisted') }}';
        var symbol   = $symbolInput.val().replace(unlisted + '_', '').replace(unlisted, '');
        if ($isListedButton.html() === 'Listed') {
            $symbolInput.val(unlisted + '_' + symbol);
            $isListedButton.html('Unlisted');
        } else {
            $symbolInput.val(symbol);
            $isListedButton.html('Listed');
        }
        $getFinanceData.toggle();
    });

    var applySmartPrefill = function(symbol)
    {
        var myFetch = ++fetchCounter;
        $.ajax({
            type: 'GET',
            url:  "{{ url('/get-finance-data') }}",
            data: {
                symbol:     symbol,
                timestamp:  $timestampPickerInput.val(),
                trade_id:   $editTradeForm.find('[name="id"]').val(),
                account_id: $accountSelect.val()
            },
            success: function(data)
            {
                if (myFetch !== fetchCounter) return;
                $getFinanceData.addClass('text-success').removeClass('text-danger')
                    .attr('data-bs-original-title', 'Get Finance Data');

                fetchedSuggestion = data.suggestion;
                storeFetchedData(data);

                showFetchedTradeCurrency(data);
                $fetchedUnitPrice.find('span').text(data.price)
                    .attr('data-bs-original-title', data.quote_timestamp).end().show();

                // Trade currency tracks the symbol, so keep the select in sync.
                setFetchedTradeCurrency(data);

                // Edit: the unit price is already set, so the description can be
                // regenerated into the label without mutating any user input.
                if (labelOnly) {
                    showFetchedDescription();
                    window.handleAvailableQuantity(data.available_quantity);
                    return;
                }

                if (!urlParamsPrefilled) {
                    $unitPriceInput.val(data.price);
                }

                var descIsAuto = !$descriptionInput.val()
                    || $descriptionInput.val() === lastAutoDescription;
                if (descIsAuto) {
                    // Empty (e.g. order-to-trade conversion, which prefills the unit
                    // price but no description) or still the previous auto-note:
                    // regenerate from the current unit price input.
                    applyAutoDescription(parseFloat($unitPriceInput.val()));
                } else {
                    lastAutoDescription = $descriptionInput.val() || null;
                }

                window.handleAvailableQuantity(data.available_quantity);
            },
            error: function(jqXHR)
            {
                if (myFetch !== fetchCounter) return;
                $getFinanceData.addClass('text-danger').removeClass('text-success')
                    .attr('data-bs-original-title', jqXHR.responseJSON.message);

                $('#fetched-symbol-name').find('span').text('').end().hide();
                $fetchedTradeCurrency.find('span').text('').end().hide();
                $fetchedUnitPrice.find('span').text('')
                    .attr('data-bs-original-title', '').end().hide();
                $fetchedDescription.find('span').text('').end().hide();

                window.handleAvailableQuantity(null);
            }
        });
    };

    $unitPriceInput.on('input', function()
    {
        if (labelOnly) { showFetchedDescription(); return; }

        if (!fetchedPrice || !fetchedHigh || !fetchedLow) return;

        var unitPrice = parseFloat($(this).val());
        if (isNaN(unitPrice) || unitPrice <= 0) return;

        var noteText = buildNoteText(unitPrice, fetchedPrice,
            fetchedHigh, fetchedLow, fetchedCurrencySymbol);
        if (!noteText) return;

        var weakSignal = (fetchedSuggestion && fetchedSuggestion.weak_signal)
            || (lastAutoDescription !== null && lastAutoDescription.startsWith('weak signal; '));
        if (weakSignal) {
            noteText = 'weak signal; ' + noteText;
        }

        if ($descriptionInput.val() === lastAutoDescription || !$descriptionInput.val()) {
            $descriptionInput.val(noteText);
            lastAutoDescription = noteText;
        }
    });

    @include('myfinance2::general.scripts.partials.finance-symbol-triggers', [
        'fetchGuard' => '!$getFinanceData.is(":hidden")'
    ])
});
</script>

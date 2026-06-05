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
    var $unitPriceInput       = $('#unit_price');
    var $descriptionInput     = $('#description');

    var fetchedSuggestion   = null;
    var lastAutoDescription = null;
    var fetchCounter        = 0;
    var urlParamsPrefilled  = $unitPriceInput.val() !== '';

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

                $fetchedTradeCurrency.find('span').text(data.currency).end().show();
                setFetchedTradeCurrency(data);

                $fetchedUnitPrice.find('span').text(data.price)
                    .attr('data-bs-original-title', data.quote_timestamp).end().show();

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

                window.handleAvailableQuantity(null);
            }
        });
    };

    $unitPriceInput.on('input', function()
    {
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

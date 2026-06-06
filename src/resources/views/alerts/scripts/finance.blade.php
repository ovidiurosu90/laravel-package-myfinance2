<script type="module">
$(document).ready(function()
{
    @include('myfinance2::general.scripts.partials.finance-utils')

    var $symbolInput        = $('#symbol-input');
    var $targetPriceInput   = $('#target_price');
    var $fetchedTargetPrice = $('#fetched-target-price');
    var $fetchedNotes       = $('#fetched-notes');
    var $notesTextarea      = $('#notes');

    var lastAutoNotes      = null;
    var fetchCounter       = 0;
    var urlParamsPrefilled = $targetPriceInput.val() !== '';

    // In label-only mode (edit) the fetch result is surfaced in the label
    // section to the right of the inputs; it never mutates the form inputs.
    var labelOnly          = {{ !empty($labelOnly) ? 'true' : 'false' }};

    var showFetchedCurrentPrice = function(data)
    {
        $fetchedTargetPrice.find('span')
            .text(data.price)
            .attr('data-bs-original-title', data.quote_timestamp)
            .end().show();
    };

    // Label-only: render the note that would be generated for the current
    // target price next to the Notes field, without touching the textarea.
    var showFetchedNotes = function()
    {
        if (!fetchedPrice || !fetchedHigh || !fetchedLow) return;

        var targetPrice = parseFloat($targetPriceInput.val());
        if (isNaN(targetPrice) || targetPrice <= 0) { $fetchedNotes.hide(); return; }

        var noteText = buildNoteText(targetPrice, fetchedPrice,
            fetchedHigh, fetchedLow, fetchedCurrencySymbol);
        if (!noteText) return;

        $fetchedNotes.find('span').text(noteText).end().show();
    };

    var applySmartPrefill = function(symbol)
    {
        var myFetch = ++fetchCounter;
        $.ajax({
            type: 'GET',
            url:  "{{ url('/get-finance-data') }}",
            data: { symbol: symbol },
            success: function(data)
            {
                if (myFetch !== fetchCounter) return;
                storeFetchedData(data);

                if (labelOnly) {
                    showFetchedCurrentPrice(data);
                    showFetchedTradeCurrency(data);
                    // Trade currency tracks the symbol, so keep the select in sync.
                    setFetchedTradeCurrency(data);
                    showFetchedNotes();
                    return;
                }

                var s = data.suggestion;

                showFetchedTradeCurrency(data);

                var alertTypeSelectize = $('#alert_type-select')[0].selectize;
                if (alertTypeSelectize && !alertTypeSelectize.getValue()) {
                    alertTypeSelectize.setValue(
                        s.action === 'BUY' ? 'PRICE_BELOW' : 'PRICE_ABOVE'
                    );
                }

                if (urlParamsPrefilled) {
                    showFetchedCurrentPrice(data);
                } else {
                    $targetPriceInput.val(s.limit_price);
                    setFetchedTradeCurrency(data);

                    // The suggestion bumps the target above the current price
                    // (the +X% recommendation); surface the current price next to
                    // the label so the markup is clearly a recommendation.
                    if (parseFloat(s.limit_price) > parseFloat(data.price)) {
                        showFetchedCurrentPrice(data);
                    } else {
                        $fetchedTargetPrice.hide();
                    }
                }

                var currentTargetPrice = parseFloat($targetPriceInput.val());
                if (!isNaN(currentTargetPrice) && fetchedHigh && fetchedLow) {
                    var noteText = buildNoteText(currentTargetPrice, fetchedPrice,
                        fetchedHigh, fetchedLow, fetchedCurrencySymbol);
                    if (noteText) {
                        $notesTextarea.val(noteText);
                        lastAutoNotes = noteText;
                    }
                }
            },
        });
    };

    $targetPriceInput.on('input', function()
    {
        if (labelOnly) { showFetchedNotes(); return; }
        if (!fetchedPrice || !fetchedHigh || !fetchedLow) return;

        var targetPrice = parseFloat($(this).val());
        if (isNaN(targetPrice) || targetPrice <= 0) return;

        var newNotes = buildNoteText(targetPrice, fetchedPrice,
            fetchedHigh, fetchedLow, fetchedCurrencySymbol);
        if (!newNotes) return;

        if ($notesTextarea.val() === lastAutoNotes || !$notesTextarea.val()) {
            $notesTextarea.val(newNotes);
            lastAutoNotes = newNotes;
        }
    });

    @include('myfinance2::general.scripts.partials.finance-symbol-triggers')
});
</script>

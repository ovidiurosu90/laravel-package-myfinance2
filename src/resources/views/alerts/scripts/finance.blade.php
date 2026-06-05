<script type="module">
$(document).ready(function()
{
    @include('myfinance2::general.scripts.partials.finance-utils')

    var $symbolInput        = $('#symbol-input');
    var $targetPriceInput   = $('#target_price');
    var $fetchedTargetPrice = $('#fetched-target-price');
    var $notesTextarea      = $('#notes');

    var lastAutoNotes      = null;
    var fetchCounter       = 0;
    var urlParamsPrefilled = $targetPriceInput.val() !== '';

    var showFetchedCurrentPrice = function(data)
    {
        $fetchedTargetPrice.find('span')
            .text(data.price)
            .attr('data-bs-original-title', data.quote_timestamp)
            .end().show();
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
                var s = data.suggestion;
                storeFetchedData(data);

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

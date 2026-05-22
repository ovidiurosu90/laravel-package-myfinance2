<script type="module">
$(document).ready(function()
{
    @include('myfinance2::general.scripts.partials.finance-utils')

    var $symbolInput      = $('#symbol-input');
    var $targetPriceInput = $('#target_price');
    var $notesTextarea    = $('#notes');

    var lastAutoNotes = null;
    var fetchCounter  = 0;

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

                $targetPriceInput.val(s.limit_price);

                setFetchedTradeCurrency(data);

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

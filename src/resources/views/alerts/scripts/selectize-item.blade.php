<script type="module">
$(document).ready(function ()
{
    var tradeCurrencies = {!! json_encode($tradeCurrencies) !!};
    var tradeCurrenciesById = {};
    for (let i in tradeCurrencies) {
        tradeCurrenciesById[tradeCurrencies[i]['id']] = tradeCurrencies[i];
    }

    var $symbolSelect = $("#symbol-select").selectize({
        placeholder: ' {{ trans('myfinance2::alerts.forms.item-form.symbol.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
    });

    var symbolInitialValue = @json($symbol ?? '');
    var symbolSelectize = $symbolSelect[0].selectize;
    if (symbolSelectize && symbolInitialValue) {
        if (!symbolSelectize.options[symbolInitialValue]) {
            symbolSelectize.addOption({ value: symbolInitialValue, text: symbolInitialValue });
        }
        symbolSelectize.setValue(symbolInitialValue, true);
    }

    var $alertTypeSelect = $("#alert_type-select").selectize({
        placeholder: ' {{ trans('myfinance2::alerts.forms.item-form.alert_type.placeholder') }} ',
        allowClear: true,
        create: false,
        highlight: true,
    });

    var $statusSelect = $("#status-select").selectize({
        placeholder: ' {{ trans('myfinance2::alerts.forms.item-form.status.placeholder') }} ',
        allowClear: true,
        create: false,
        highlight: true,
    });

    var $tradeCurrencySelect = $("#trade_currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::alerts.forms.item-form.trade_currency.placeholder') }} ',
        allowClear: true,
        create: false,
        highlight: true,
        diacritics: true,
        onChange: function (value)
        {
            var tradeCurrency = value ? tradeCurrenciesById[value] : null;
            var $label = $('#trade_currency-label-tooltip');

            if (tradeCurrency) {
                $label.html(tradeCurrency['display_code']);
            } else {
                $label.html('&curren;');
            }
        }
    });

    var tradeCurrencyInitialValue = $tradeCurrencySelect[0].selectize.getValue();
    if (tradeCurrencyInitialValue) {
        var initialCurrency = tradeCurrenciesById[tradeCurrencyInitialValue];
        if (initialCurrency) {
            $('#trade_currency-label-tooltip').html(initialCurrency['display_code']);
        }
    }

    // Keep the notes percentage in sync when target_price changes.
    // Works for existing notes of the form "X% below NY high of PRICE CURRENCY [on DATE]".
    var $notesTextarea    = $('#notes');
    var $targetPriceInput = $('#target_price');
    var autoNotes         = $notesTextarea.val();
    var parsedHighValue   = null;

    (function()
    {
        var match = autoNotes.match(/^(\d+\.?\d*)% below \d+Y high of ([\d,]+\.?\d*)/);
        if (match) {
            parsedHighValue = parseFloat(match[2].replace(/,/g, ''));
        }
    })();

    $targetPriceInput.on('input', function()
    {
        if (!parsedHighValue) return;
        if ($notesTextarea.val() !== autoNotes) return;

        var targetPrice = parseFloat($(this).val());
        if (isNaN(targetPrice) || targetPrice <= 0) return;

        var pct      = ((parsedHighValue - targetPrice) / parsedHighValue * 100).toFixed(1);
        var newNotes = autoNotes.replace(/^\d+\.?\d*/, pct);
        $notesTextarea.val(newNotes);
        autoNotes = newNotes;
    });
});
</script>

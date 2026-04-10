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
});
</script>

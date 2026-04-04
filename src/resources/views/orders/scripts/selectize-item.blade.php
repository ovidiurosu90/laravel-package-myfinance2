<script type="module">
$(document).ready(function ()
{
    var accounts = {!! json_encode($accounts) !!};
    var accountsById = {};
    for (let i in accounts) {
        accountsById[accounts[i]['id']] = accounts[i];
    }

    var tradeCurrencies = {!! json_encode($tradeCurrencies) !!};
    var tradeCurrenciesById = {};
    for (let i in tradeCurrencies) {
        tradeCurrenciesById[tradeCurrencies[i]['id']] = tradeCurrencies[i];
    }

    var $symbolSelect = $("#symbol-select").selectize({
        placeholder: ' {{ trans('myfinance2::orders.forms.item-form.symbol.placeholder') }} ',
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

    var $actionSelect = $("#action-select").selectize({
        placeholder: ' {{ trans('myfinance2::orders.forms.item-form.action.placeholder') }} ',
        allowClear: true,
        create: false,
        highlight: true,
    });
    var actionSelectize = $actionSelect[0].selectize;

    var actionPrefill = @json($action_prefill ?? '');
    if (actionSelectize && actionPrefill) {
        actionSelectize.setValue(actionPrefill, true);
    }

    var $statusSelect = $("#status-select").selectize({
        placeholder: ' {{ trans('myfinance2::orders.forms.item-form.status.placeholder') }} ',
        allowClear: true,
        create: false,
        highlight: true,
    });

    var $accountSelect = $("#account-select").selectize({
        placeholder: ' {{ trans('myfinance2::orders.forms.item-form.account.placeholder') }} ',
        allowClear: true,
        create: false,
        highlight: true,
        diacritics: true,
        onChange: function(value)
        {
            currencyChange();
        }
    });
    var accountSelectize = $accountSelect[0].selectize;

    var $tradeCurrencySelect = $("#trade_currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::orders.forms.item-form.trade_currency.placeholder') }} ',
        allowClear: true,
        create: false,
        highlight: true,
        diacritics: true,
        onChange: function(value)
        {
            currencyChange();
        }
    });
    var tradeCurrencySelectize = $tradeCurrencySelect[0].selectize;

    var $exchangeRateInput = $('input#exchange_rate');
    var $tradeCurrencyLabelTooltip = $('#trade_currency-label-tooltip');
    var $accountCurrencyLabelTooltip = $('#account_currency-label-tooltip');

    var currencyChange = function()
    {
        var accountId = accountSelectize.getValue();
        var accountCurrency = accountId ? accountsById[accountId]['currency'] : null;
        var tradeCurrencyId = tradeCurrencySelectize.getValue();
        var tradeCurrency = tradeCurrencyId ? tradeCurrenciesById[tradeCurrencyId] : null;

        if (accountCurrency && tradeCurrency &&
            accountCurrency.iso_code == tradeCurrency.iso_code
        ) {
            $exchangeRateInput.val(1);
        }

        if (tradeCurrency) {
            $tradeCurrencyLabelTooltip.html(tradeCurrency['display_code']);
        } else {
            $tradeCurrencyLabelTooltip.html('&curren;');
        }

        if (accountCurrency) {
            $accountCurrencyLabelTooltip.html(accountCurrency['display_code']);
        } else {
            $accountCurrencyLabelTooltip.html('&curren;');
        }
    };
});
</script>

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

    var $actionSelect = $("#action-select").selectize({
        placeholder: ' {{ trans('myfinance2::trades.forms.item-form.'
                                . 'action.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true
    });
    var actionSelectize = $actionSelect[0].selectize;

    var $accountSelect = $("#account-select").selectize({
        placeholder: ' {{ trans('myfinance2::trades.forms.item-form.'
                                . 'account.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value)
        {
            currencyChange();
        }
    });
    var accountSelectize = $accountSelect[0].selectize;

    var $tradeCurrencySelect = $("#trade_currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::trades.forms.item-form.'
                                . 'trade_currency.placeholder') }} ',
        allowClear: true,
        create: true,
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

    var tradeCurrencyDisplay;
    var accountCurrencyDisplay;

    var currencyChange = function()
    {
        var accountId = accountSelectize.getValue();
        var accountCurrency = accountId ? accountsById[accountId]['currency']
                                        : null;
        var tradeCurrencyId = tradeCurrencySelectize.getValue();
        var tradeCurrency = tradeCurrencyId ? tradeCurrenciesById[tradeCurrencyId]
                                            : null;

        if (accountCurrency && tradeCurrency &&
            accountCurrency.iso_code == tradeCurrency.iso_code
        ) {
            $exchangeRateInput.val(1);
        }

        // Change the trade currency in the label tooltip
        if (tradeCurrency) {
            tradeCurrencyDisplay = tradeCurrency['display_code'];
        } else {
            tradeCurrencyDisplay = '&curren;';
        }
        $tradeCurrencyLabelTooltip.html(tradeCurrencyDisplay);

        // Change the account currency in the label tooltip
        if (accountCurrency) {
            accountCurrencyDisplay = accountCurrency['display_code'];
        } else {
            accountCurrencyDisplay = '&curren;';
        }
        $accountCurrencyLabelTooltip.html(accountCurrencyDisplay);
    };

});
</script>


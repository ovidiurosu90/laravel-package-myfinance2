<script type="module">
$(document).ready(function ()
{
    var $actionSelect = $("#action-select").selectize({
        placeholder: ' {{ trans('myfinance2::trades.forms.item-form.action.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true
    });
    var actionSelectize = $actionSelect[0].selectize;

    var $accountSelect = $("#account-select").selectize({
        placeholder: ' {{ trans('myfinance2::trades.forms.item-form.account.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true
    });
    var accountSelectize = $accountSelect[0].selectize;

    var $accountCurrencySelect = $("#account_currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::trades.forms.item-form.account_currency.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value) {
            currencyChange();
        }
    });
    var accountCurrencySelectize = $accountCurrencySelect[0].selectize;

    var $tradeCurrencySelect = $("#trade_currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::trades.forms.item-form.trade_currency.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value) {
            currencyChange();
        }
    });
    var tradeCurrencySelectize = $tradeCurrencySelect[0].selectize;

    var $exchangeRateInput = $('input#exchange_rate');
    var $tradeCurrencyLabelTooltip = $('#trade_currency-label-tooltip');
    var $accountCurrencyLabelTooltip = $('#account_currency-label-tooltip');
    var currenciesDisplay = {!! json_encode(config('general.currencies_display')) !!};
    var tradeCurrencyDisplay;
    var accountCurrencyDisplay;

    var currencyChange = function() {
        var accountCurrency = accountCurrencySelectize.getValue();
        var tradeCurrency = tradeCurrencySelectize.getValue();
        if (accountCurrency && tradeCurrency && accountCurrency == tradeCurrency) {
            $exchangeRateInput.val(1);
        }

        // Change the trade currency in the label tooltip
        if (tradeCurrency in currenciesDisplay) {
            tradeCurrencyDisplay = currenciesDisplay[tradeCurrency];
        } else {
            tradeCurrencyDisplay = tradeCurrency;
        }
        $tradeCurrencyLabelTooltip.html(tradeCurrencyDisplay);

        // Change the account currency in the label tooltip
        if (accountCurrency in currenciesDisplay) {
            accountCurrencyDisplay = currenciesDisplay[accountCurrency];
        } else {
            accountCurrencyDisplay = accountCurrency;
        }
        $accountCurrencyLabelTooltip.html(accountCurrencyDisplay);
    };

});
</script>


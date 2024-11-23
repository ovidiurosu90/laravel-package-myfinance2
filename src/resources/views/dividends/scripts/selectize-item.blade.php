<script type="module">
$(document).ready(function ()
{
    var $accountSelect = $("#account-select").selectize({
        placeholder: ' {{ trans('myfinance2::dividends.forms.item-form.account.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true
    });
    var accountSelectize = $accountSelect[0].selectize;

    var $accountCurrencySelect = $("#account_currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::dividends.forms.item-form.account_currency.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value) {
            currencyChange();
        }
    });
    var accountCurrencySelectize = $accountCurrencySelect[0].selectize;

    var $dividendCurrencySelect = $("#dividend_currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::dividends.forms.item-form.dividend_currency.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value) {
            currencyChange();
        }
    });
    var dividendCurrencySelectize = $dividendCurrencySelect[0].selectize;

    var $exchangeRateInput = $('input#exchange_rate');
    var $dividendCurrencyLabelTooltip = $('#dividend_currency-label-tooltip');
    var $accountCurrencyLabelTooltip = $('#account_currency-label-tooltip');
    var currenciesDisplay = {!! json_encode(config('general.currencies_display')) !!};
    var dividendCurrencyDisplay;
    var accountCurrencyDisplay;

    var $feeCurrencyToggle = $("#fee_currency-toggle");

    var currencyChange = function() {
        var accountCurrency = accountCurrencySelectize.getValue();
        var dividendCurrency = dividendCurrencySelectize.getValue();
        if (accountCurrency && dividendCurrency && accountCurrency == dividendCurrency) {
            $exchangeRateInput.val(1);
        }

        // Change the dividend currency in the label tooltip
        if (dividendCurrency in currenciesDisplay) {
            dividendCurrencyDisplay = currenciesDisplay[dividendCurrency];
            $feeCurrencyToggle.attr("data-offlabel", dividendCurrencyDisplay);
        } else {
            dividendCurrencyDisplay = dividendCurrency;
            $feeCurrencyToggle.attr("data-offlabel", "x");
        }
        $dividendCurrencyLabelTooltip.html(dividendCurrencyDisplay);

        // Change the account currency in the label tooltip
        if (accountCurrency in currenciesDisplay) {
            accountCurrencyDisplay = currenciesDisplay[accountCurrency];
            $feeCurrencyToggle.attr("data-onlabel", accountCurrencyDisplay);
        } else {
            accountCurrencyDisplay = accountCurrency;
            $feeCurrencyToggle.attr("data-onlabel", "x");
        }
        $accountCurrencyLabelTooltip.html(accountCurrencyDisplay);

        $feeCurrencyToggle.trigger("my-reset");
    };

});
</script>


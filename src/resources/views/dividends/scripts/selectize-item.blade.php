<script type="module">
$(document).ready(function ()
{
    var accounts = {!! json_encode($accounts) !!};
    var accountsById = {};
    for (let i in accounts) {
        accountsById[accounts[i]['id']] = accounts[i];
    }

    var dividendCurrencies = {!! json_encode($dividendCurrencies) !!};
    var dividendCurrenciesById = {};
    for (let i in dividendCurrencies) {
        dividendCurrenciesById[dividendCurrencies[i]['id']] = dividendCurrencies[i];
    }

    var $accountSelect = $("#account-select").selectize({
        placeholder: ' {{ trans('myfinance2::dividends.forms.item-form.'
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

    var $dividendCurrencySelect = $("#dividend_currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::dividends.forms.item-form.'
                                . 'dividend_currency.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value)
        {
            currencyChange();
        }
    });
    var dividendCurrencySelectize = $dividendCurrencySelect[0].selectize;

    var $exchangeRateInput = $('input#exchange_rate');
    var $dividendCurrencyLabelTooltip = $('#dividend_currency-label-tooltip');
    var $accountCurrencyLabelTooltip = $('#account_currency-label-tooltip');
    var $feeCurrencyToggle = $("#fee_currency-toggle");

    var dividendCurrencyDisplay;
    var accountCurrencyDisplay;

    var currencyChange = function()
    {
        var accountId = accountSelectize.getValue();
        var accountCurrency = accountId ? accountsById[accountId]['currency']
                                        : null;
        var dividendCurrencyId = dividendCurrencySelectize.getValue();
        var dividendCurrency = dividendCurrencyId ?
                               dividendCurrenciesById[dividendCurrencyId] : null;

        if (accountCurrency && dividendCurrency &&
            accountCurrency.iso_code == dividendCurrency.iso_code
        ) {
            $exchangeRateInput.val(1);
        }

        // Change the dividend currency in the label tooltip
        if (dividendCurrency) {
            dividendCurrencyDisplay = dividendCurrency['display_code'];
            $feeCurrencyToggle.attr("data-offlabel", dividendCurrencyDisplay);
        } else {
            dividendCurrencyDisplay = '&curren;';
            $feeCurrencyToggle.attr("data-offlabel", "x");
        }
        $dividendCurrencyLabelTooltip.html(dividendCurrencyDisplay);

        // Change the account currency in the label tooltip
        if (accountCurrency) {
            accountCurrencyDisplay = accountCurrency['display_code'];
            $feeCurrencyToggle.attr("data-onlabel", accountCurrencyDisplay);
        } else {
            accountCurrencyDisplay = '&curren;';
            $feeCurrencyToggle.attr("data-onlabel", "x");
        }
        $accountCurrencyLabelTooltip.html(accountCurrencyDisplay);

        $feeCurrencyToggle.trigger("my-reset");
    };

});
</script>


<script type="module">
$(document).ready(function ()
{
    var $accountSelect = $("#account-select").selectize({
        placeholder: ' {{ trans('myfinance2::cashbalances.forms.item-form.account.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true
    });
    var accountSelectize = $accountSelect[0].selectize;

    var $accountCurrencySelect = $("#account_currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::cashbalances.forms.item-form.account_currency.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value) {
            currencyChange();
        }
    });
    var accountCurrencySelectize = $accountCurrencySelect[0].selectize;

    var $accountCurrencyLabelTooltip = $('#account_currency-label-tooltip');
    var currenciesDisplay = {!! json_encode(config('general.currencies_display')) !!};
    var accountCurrencyDisplay;

    var currencyChange = function() {
        var accountCurrency = accountCurrencySelectize.getValue();

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


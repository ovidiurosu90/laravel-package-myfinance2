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

    // Placeholders — reassigned when the disable-auto-FX section is present
    var autoSelectPairedAccount = function() {};
    var updateAutoFxSectionVisibility = function() {};

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

        updateAutoFxSectionVisibility();
    };

    // Disable Auto FX Rate — paired account logic
    var $disableAutoFxCheckbox = $('#disable-auto-fx-checkbox');
    if ($disableAutoFxCheckbox.length) {
        var ledgerAccounts = {!! json_encode($ledgerAccounts ?? []) !!};
        var ledgerAccountsById = {};
        ledgerAccounts.forEach(function(a) { ledgerAccountsById[a.id] = a; });

        var $pairedAccountSelect = $('#paired-account-select').selectize({
            placeholder: ' {{ trans('myfinance2::trades.forms.item-form.paired_account.placeholder') }} ',
            allowClear: true,
            highlight: true,
            diacritics: true
        });
        var pairedAccountSelectize = $pairedAccountSelect[0].selectize;

        // Common ISO currency codes to strip from account names
        var currencyPattern = new RegExp(
            '\\s+(EUR|USD|GBP|CHF|JPY|CAD|AUD|SEK|DKK|NOK|HKD|SGD|NZD|'
            + 'MXN|BRL|INR|CNY|TWD|KRW|ZAR|PLN|TRY|HUF|CZK|RUB|IDR|PHP|MYR|THB)\\s*$',
            'i'
        );

        autoSelectPairedAccount = function()
        {
            var accountId = accountSelectize.getValue();
            var tradeCurrencyId = tradeCurrencySelectize.getValue();
            if (!accountId || !tradeCurrencyId) {
                return;
            }

            var account = accountsById[accountId];
            var tradeCurrency = tradeCurrenciesById[tradeCurrencyId];
            if (!account || !tradeCurrency) {
                return;
            }

            var tradeCurrencyIso = tradeCurrency.iso_code;
            var accountBase = account.name.replace(currencyPattern, '').trim().toLowerCase();

            for (var id in ledgerAccountsById) {
                var candidate = ledgerAccountsById[id];
                var candidateBase = candidate.name.replace(currencyPattern, '').trim().toLowerCase();
                var candidateCurrencyIso = candidate.currency
                    ? candidate.currency.iso_code : null;

                if (accountBase.length > 0
                    && accountBase === candidateBase
                    && candidateCurrencyIso === tradeCurrencyIso
                ) {
                    pairedAccountSelectize.setValue(candidate.id);
                    return;
                }
            }
        };

        updateAutoFxSectionVisibility = function()
        {
            var accountId = accountSelectize.getValue();
            var tradeCurrencyId = tradeCurrencySelectize.getValue();
            var accountCurrency = accountId ? accountsById[accountId]['currency'] : null;
            var tradeCurrency = tradeCurrencyId ? tradeCurrenciesById[tradeCurrencyId] : null;

            var $section = $('#disable-auto-fx-section');
            var currenciesDiffer = accountCurrency && tradeCurrency
                && accountCurrency.iso_code !== tradeCurrency.iso_code;

            if (currenciesDiffer) {
                $section.show();
            } else {
                $section.hide();
                if ($disableAutoFxCheckbox.is(':checked')) {
                    $disableAutoFxCheckbox.prop('checked', false);
                    $('#auto-fx-toggle-label').text('Off');
                    $('#paired-account-wrapper').hide();
                }
            }
        };

        $disableAutoFxCheckbox.on('change', function()
        {
            var isChecked = $(this).is(':checked');
            $('#auto-fx-toggle-label').text(isChecked ? 'On' : 'Off');

            var $wrapper = $('#paired-account-wrapper');
            if (isChecked) {
                $wrapper.show();
                autoSelectPairedAccount();
            } else {
                $wrapper.hide();
            }
        });

        // Set initial visibility based on pre-filled values
        updateAutoFxSectionVisibility();
    }

});
</script>


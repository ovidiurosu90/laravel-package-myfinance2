<script type="module">
$(document).ready(function ()
{
    var accounts = {!! json_encode($accounts) !!};
    var accountsById = {};
    for (let i in accounts) {
        accountsById[accounts[i]['id']] = accounts[i];
    }

    localStorage.setItem('parentTransactionsJSON',
        JSON.stringify(@json($rootTransactions)));

    var $typeSelect = $("#transaction-type-select").selectize({
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.'
                                . 'type.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value)
        {
            currencyChange();
        }
    });
    var typeSelectize = $typeSelect[0] ? $typeSelect[0].selectize : null;

    var $debitAccountSelect = $("#transaction-debit_account-select").selectize({
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.'
                                . 'debit_account.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value)
        {
            currencyChange();
        }
    });
    var debitAccountSelectize = $debitAccountSelect[0] ?
            $debitAccountSelect[0].selectize : null;

    var $toggleTransactionDebitAccountSelect =
        $("#toggle-transaction-debit_account-select");
    $toggleTransactionDebitAccountSelect.change(function()
    {
        if ($(this).is(':checked')) {
            debitAccountSelectize.unlock();
        } else {
            debitAccountSelectize.lock();
        }
    });

    var $creditAccountSelect = $("#transaction-credit_account-select").selectize({
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.'
                                . 'credit_account.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value)
        {
            currencyChange();
        }
    });
    var creditAccountSelectize = $creditAccountSelect[0] ?
            $creditAccountSelect[0].selectize : null;

    var $toggleTransactionCreditAccountSelect =
        $("#toggle-transaction-credit_account-select");
    $toggleTransactionCreditAccountSelect.change(function()
    {
        if ($(this).is(':checked')) {
            creditAccountSelectize.unlock();
        } else {
            creditAccountSelectize.lock();
        }
    });

    var $exchangeRateInput = $('input#exchange_rate');
    var $amountCurrencyLabelTooltip = $('#amount_currency-label-tooltip');
    var $feeCurrencyLabelTooltip = $('#fee_currency-label-tooltip');
    var amountCurrencyDisplay;
    var feeCurrencyDisplay;

    var $toggleTransactionExchangeRateInput =
        $("#toggle-transaction-exchange_rate-input");
    $toggleTransactionExchangeRateInput.change(function()
    {
        if ($(this).is(':checked')) {
            $exchangeRateInput.prop('readonly', false);
        } else {
            $exchangeRateInput.prop('readonly', true);
        }
    });

    var currencyChange = function()
    {
        var debitAccountId = debitAccountSelectize.getValue();
        var debitCurrency = debitAccountId
                                ? accountsById[debitAccountId]['currency']
                                : null;

        var creditAccountId = creditAccountSelectize.getValue();
        var creditCurrency = creditAccountId
                                ? accountsById[creditAccountId]['currency']
                                : null;

        if (debitCurrency && creditCurrency &&
            debitCurrency.iso_code == creditCurrency.iso_code
        ) {
            $exchangeRateInput.val(1);
            $toggleTransactionExchangeRateInput.bootstrapToggle('off');
        } else {
            $exchangeRateInput.val('');
            $toggleTransactionExchangeRateInput.bootstrapToggle('on');
        }

        var type = typeSelectize.getValue();
        if (!type) {
            $amountCurrencyLabelTooltip.html('');
            $feeCurrencyLabelTooltip.html('');
            return;
        }

        // Change the amount & fee currency in the label tooltip
        if (type == 'DEBIT' && debitCurrency) {
            amountCurrencyDisplay = debitCurrency['display_code'];
            feeCurrencyDisplay = debitCurrency['display_code'];
        } else if (type == 'CREDIT' && creditCurrency) {
            amountCurrencyDisplay = creditCurrency['display_code'];
            feeCurrencyDisplay = creditCurrency['display_code'];
        } else {
            $amountCurrencyLabelTooltip.html('');
            $feeCurrencyLabelTooltip.html('');
            return;
        }

        $amountCurrencyLabelTooltip.html(amountCurrencyDisplay);
        $feeCurrencyLabelTooltip.html(feeCurrencyDisplay);

    }; // end currencyChange

    var $calculatedAmount = $('#calculated-amount');
    // var $timestampPicker = $('#timestamp-picker');

    var clearParentChilds = function()
    {
        window.timestampPicker1.clear();
        // $timestampPicker.find('>input').val('');
        debitAccountSelectize.setValue('');
        creditAccountSelectize.setValue('');
        $exchangeRateInput.val('');
        $calculatedAmount.hide();
    }; // end clearParentChilds

    var unlockParentChilds = function()
    {
        debitAccountSelectize.unlock();
        creditAccountSelectize.unlock();
        $exchangeRateInput.prop('readonly', false);

        $toggleTransactionDebitAccountSelect.bootstrapToggle('on');
        $toggleTransactionCreditAccountSelect.bootstrapToggle('on');
        $toggleTransactionExchangeRateInput.bootstrapToggle('on');
    }; // end unlockParentChilds

    var updateParentChilds = function(parentTransaction)
    {
        debitAccountSelectize.setValue(parentTransaction.debit_account_id);
        creditAccountSelectize.setValue(parentTransaction.credit_account_id);
        $exchangeRateInput.val(parentTransaction.exchange_rate);
    }; // end updateParentChilds

    var lockParentChilds = function()
    {
        debitAccountSelectize.lock();
        creditAccountSelectize.lock();
        $exchangeRateInput.prop('readonly', true);

        $toggleTransactionDebitAccountSelect.bootstrapToggle('off');
        $toggleTransactionCreditAccountSelect.bootstrapToggle('off');
        $toggleTransactionExchangeRateInput.bootstrapToggle('off');
    }; // end lockParentChilds

    var setParentTransactionTimestamp = function(timestamp)
    {
        var parentTransactionDate = new Date(timestamp);
        parentTransactionDate.setMinutes(parentTransactionDate.getMinutes() + 1);
        // $timestampPicker.tempusDominus.Constructor.prototype.updateOptions({
        window.timestampPicker1.updateOptions({
            restrictions: {minDate: parentTransactionDate},
            viewDate: parentTransactionDate,
            localization: {format: 'yyyy-MM-dd HH:mm:ss'},
            display: {buttons: {today: true}}
        }, true);
    }; // end setParentTransactionTimestamp

    var setCalculatedAmount = function(parentTransaction)
    {
        $calculatedAmount.find('span').text(
            Math.round(parentTransaction.amount * parentTransaction.exchange_rate
                * 100) / 100); // round to 2 decimals
        $calculatedAmount.show();
    }; // end setCalculatedAmount

    var getTransactionFromLocalStorage = function(id)
    {
        var parentTransactionsJSON = localStorage.getItem('parentTransactionsJSON');
        if (!parentTransactionsJSON) {
            return null;
        }
        var parentTransactions = JSON.parse(parentTransactionsJSON);
        for (let i = 0; i < parentTransactions.length; i++) {
            if (parentTransactions[i].id == id) {
                return parentTransactions[i];
            }
        }
        return null;
    }; // end getTransactionFromLocalStorage

    $("#transaction-parent-select").selectize({
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.'
                                . 'parent.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        allowEmptyOption: true,

        onInitialize: function()
        {
            var value = this.getValue();
            if (!value) {
                unlockParentChilds();
                return;
            }

            var parentTransaction = getTransactionFromLocalStorage(value);
            if (parentTransaction) {
                setCalculatedAmount(parentTransaction);
                lockParentChilds();
            }
        }, // end onInitialize

        onChange: function(value)
        {
            if (!value) {
                clearParentChilds();
                window.timestampPicker1.updateOptions({
                    restrictions: {minDate: undefined},
                    viewDate: new Date(),
                    localization: {format: 'yyyy-MM-dd HH:mm:ss'},
                    display: {buttons: {today: true}}
                }, true);
                unlockParentChilds();
                return;
            }

            var parentTransaction = getTransactionFromLocalStorage(value);
            if (parentTransaction) {
                setParentTransactionTimestamp(parentTransaction.timestamp);
                setCalculatedAmount(parentTransaction);
                updateParentChilds(parentTransaction);
                lockParentChilds();
            }
        } // end onChange
    }); // end $("#transaction-parent-select")
}); // end $(document).ready
</script>


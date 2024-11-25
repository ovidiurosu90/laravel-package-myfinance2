<script type="module">
$(document).ready(function ()
{
    var $typeSelect = $("#transaction-type-select").selectize({
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.type.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true
    });
    var typeSelectize = $typeSelect[0] ? $typeSelect[0].selectize : null;

    var $debitAccountSelect = $("#transaction-debit_account-select").selectize({
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.debit_account.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true
    });
    var debitAccountSelectize = $debitAccountSelect[0] ? $debitAccountSelect[0].selectize : null;

    var $toggleTransactionDebitAccountSelect = $("#toggle-transaction-debit_account-select");
    $toggleTransactionDebitAccountSelect.change(function()
    {
        if ($(this).is(':checked')) {
            debitAccountSelectize.unlock();
        } else {
            debitAccountSelectize.lock();
        }
    });

    var $creditAccountSelect = $("#transaction-credit_account-select").selectize({
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.credit_account.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true
    });
    var creditAccountSelectize = $creditAccountSelect[0] ? $creditAccountSelect[0].selectize : null;

    var $toggleTransactionCreditAccountSelect = $("#toggle-transaction-credit_account-select");
    $toggleTransactionCreditAccountSelect.change(function()
    {
        if ($(this).is(':checked')) {
            creditAccountSelectize.unlock();
        } else {
            creditAccountSelectize.lock();
        }
    });

    var $debitCurrencySelect = $("#transaction-debit_currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.debit_currency.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value)
        {
            currencyChange();
        }
    }); // end $debitCurrencySelect
    var debitCurrencySelectize = $debitCurrencySelect[0] ? $debitCurrencySelect[0].selectize : null;

    var $toggleTransactionDebitCurrencySelect = $("#toggle-transaction-debit_currency-select");
    $toggleTransactionDebitCurrencySelect.change(function()
    {
        if ($(this).is(':checked')) {
            debitCurrencySelectize.unlock();
        } else {
            debitCurrencySelectize.lock();
        }
    });

    var $creditCurrencySelect = $("#transaction-credit_currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.credit_currency.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value)
        {
            currencyChange();
        }
    }); // end $creditCurrencySelect
    var creditCurrencySelectize = $creditCurrencySelect[0] ? $creditCurrencySelect[0].selectize : null;

    var $toggleTransactionCreditCurrencySelect = $("#toggle-transaction-credit_currency-select");
    $toggleTransactionCreditCurrencySelect.change(function()
    {
        if ($(this).is(':checked')) {
            creditCurrencySelectize.unlock();
        } else {
            creditCurrencySelectize.lock();
        }
    });

    var $exchangeRateInput = $('input#exchange_rate');
    var $toggleTransactionExchangeRateInput = $("#toggle-transaction-exchange_rate-input");
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
        var debitCurrency = debitCurrencySelectize.getValue();
        var creditCurrency = creditCurrencySelectize.getValue();
        if (debitCurrency && creditCurrency && debitCurrency == creditCurrency) {
            $exchangeRateInput.val(1);
            $toggleTransactionExchangeRateInput.bootstrapToggle('off');
        } else {
            $exchangeRateInput.val('');
            $toggleTransactionExchangeRateInput.bootstrapToggle('on');
        }
    }; // end currencyChange

    var $calculatedAmount = $('#calculated-amount');
    var $timestampPicker = $('#timestamp-picker');
    localStorage.setItem('parentTransactionsJSON', JSON.stringify(@json($rootTransactions)));

    var clearParentChilds = function()
    {
        window.timestampPicker1.clear();
        // $timestampPicker.find('>input').val('');
        debitAccountSelectize.setValue('');
        debitCurrencySelectize.setValue('');
        creditAccountSelectize.setValue('');
        creditCurrencySelectize.setValue('');
        $exchangeRateInput.val('');
        $calculatedAmount.hide();
    }; // end clearParentChilds

    var unlockParentChilds = function()
    {
        debitAccountSelectize.unlock();
        debitCurrencySelectize.unlock();
        creditAccountSelectize.unlock();
        creditCurrencySelectize.unlock();
        $exchangeRateInput.prop('readonly', false);

        $toggleTransactionDebitAccountSelect.bootstrapToggle('on');
        $toggleTransactionDebitCurrencySelect.bootstrapToggle('on');
        $toggleTransactionCreditAccountSelect.bootstrapToggle('on');
        $toggleTransactionCreditCurrencySelect.bootstrapToggle('on');
        $toggleTransactionExchangeRateInput.bootstrapToggle('on');
    }; // end unlockParentChilds

    var updateParentChilds = function(parentTransaction)
    {
        debitAccountSelectize.setValue(parentTransaction.debit_account);
        debitCurrencySelectize.setValue(parentTransaction.debit_currency);
        creditAccountSelectize.setValue(parentTransaction.credit_account);
        creditCurrencySelectize.setValue(parentTransaction.credit_currency);
        $exchangeRateInput.val(parentTransaction.exchange_rate);
    }; // end updateParentChilds

    var lockParentChilds = function()
    {
        debitAccountSelectize.lock();
        debitCurrencySelectize.lock();
        creditAccountSelectize.lock();
        creditCurrencySelectize.lock();
        $exchangeRateInput.prop('readonly', true);

        $toggleTransactionDebitAccountSelect.bootstrapToggle('off');
        $toggleTransactionDebitCurrencySelect.bootstrapToggle('off');
        $toggleTransactionCreditAccountSelect.bootstrapToggle('off');
        $toggleTransactionCreditCurrencySelect.bootstrapToggle('off');
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
            Math.round(parentTransaction.amount * parentTransaction.exchange_rate));
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
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.parent.placeholder') }} ',
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
                // $timestampPicker.tempusDominus.Constructor.prototype.updateOptions({
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


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
    var typeSelectize = $typeSelect[0].selectize;

    var $debitAccountSelect = $("#transaction-debit_account-select").selectize({
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.debit_account.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true
    });
    var debitAccountSelectize =  $debitAccountSelect[0].selectize;
    $("#enable-transaction-debit_account-select").click(function() {
        debitAccountSelectize.unlock();
    });

    var $creditAccountSelect = $("#transaction-credit_account-select").selectize({
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.credit_account.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true
    });
    var creditAccountSelectize = $creditAccountSelect[0].selectize;
    $("#enable-transaction-credit_account-select").click(function() {
        creditAccountSelectize.unlock();
    });

    var $debitCurrencySelect = $("#transaction-debit_currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.debit_currency.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value) {
            currencyChange();
        }
    });
    var debitCurrencySelectize = $debitCurrencySelect[0].selectize;
    $("#enable-transaction-debit_currency-select").click(function() {
        debitCurrencySelectize.unlock();
    });

    var $creditCurrencySelect = $("#transaction-credit_currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.credit_currency.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value) {
            currencyChange();
        }
    });
    var creditCurrencySelectize = $creditCurrencySelect[0].selectize;
    $("#enable-transaction-credit_currency-select").click(function() {
        creditCurrencySelectize.unlock();
    });

    var $exchangeRateInput = $('input#exchange_rate');
    $("#enable-transaction-exchange_rate-input").click(function() {
        $exchangeRateInput.prop('readonly', false);
    });

    var currencyChange = function() {
        var debitCurrency = debitCurrencySelectize.getValue();
        var creditCurrency = creditCurrencySelectize.getValue();
        if (debitCurrency && creditCurrency && debitCurrency == creditCurrency) {
            $exchangeRateInput.val(1);
        }
    };

    var $calculatedAmount = $('#calculated-amount');
    var $timestampPicker = $('#timestamp-picker');
    localStorage.setItem('parentTransactionsJSON', JSON.stringify(@json($rootTransactions)));
    var clearParentChilds = function() {
        window.timestampPicker1.clear();
        // $timestampPicker.find('>input').val('');
        debitAccountSelectize.setValue('');
        debitCurrencySelectize.setValue('');
        creditAccountSelectize.setValue('');
        creditCurrencySelectize.setValue('');
        $exchangeRateInput.val('');
        $calculatedAmount.hide();
    };
    var unlockParentChilds = function() {
        debitAccountSelectize.unlock();
        debitCurrencySelectize.unlock();
        creditAccountSelectize.unlock();
        creditCurrencySelectize.unlock();
        $exchangeRateInput.prop('readonly', false);
    };
    var updateParentChilds = function(parentTransaction) {
        debitAccountSelectize.setValue(parentTransaction.debit_account);
        debitCurrencySelectize.setValue(parentTransaction.debit_currency);
            creditAccountSelectize.setValue(parentTransaction.credit_account);
            creditCurrencySelectize.setValue(parentTransaction.credit_currency);
            $exchangeRateInput.val(parentTransaction.exchange_rate);
        };
        var lockParentChilds = function() {
            debitAccountSelectize.lock();
            debitCurrencySelectize.lock();
            creditAccountSelectize.lock();
            creditCurrencySelectize.lock();
            $exchangeRateInput.prop('readonly', true);
        };
        var setParentTransactionTimestamp = function(timestamp) {
            var parentTransactionDate = new Date(timestamp);
            parentTransactionDate.setMinutes(parentTransactionDate.getMinutes() + 1);
            // $timestampPicker.tempusDominus.Constructor.prototype.updateOptions({
            window.timestampPicker1.updateOptions({
                restrictions: {minDate: parentTransactionDate},
                viewDate: parentTransactionDate,
                localization: {format: 'yyyy-MM-dd HH:mm:ss'},
                display: {buttons: {today: true}}
            }, true);
        };
        var setCalculatedAmount = function(parentTransaction) {
            $calculatedAmount.find('span').text(
                Math.round(parentTransaction.amount * parentTransaction.exchange_rate));
            $calculatedAmount.show();
        };

        var getTransactionFromLocalStorage = function(id) {
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
        }

        $("#transaction-parent-select").selectize({
            placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.parent.placeholder') }} ',
            allowClear: true,
            create: true,
            highlight: true,
            diacritics: true,
            allowEmptyOption: true,

            onInitialize: function() {
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
            },
            onChange: function(value) {
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
            }
        });
    });
</script>


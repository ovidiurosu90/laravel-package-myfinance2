<script type="module">
$(document).ready(function ()
{
    var accountCurrencyDefaults = {!! json_encode(config('general.account_currency_defaults')) !!};
    $('#transaction-debit_account-select').on('change', function() {
        if (this.value in accountCurrencyDefaults) {
            var $select = $('#transaction-debit_currency-select').selectize();
            var selectize = $select[0].selectize;
            selectize.setValue(accountCurrencyDefaults[this.value]);
        }
    });
    $('#transaction-credit_account-select').on('change', function() {
        if (this.value in accountCurrencyDefaults) {
            var $select = $('#transaction-credit_currency-select').selectize();
            var selectize = $select[0].selectize;
            selectize.setValue(accountCurrencyDefaults[this.value]);
        }
    });
});
</script>


<script type="module">
$(document).ready(function ()
{
    var accountCurrencyDefaults = {!! json_encode(config('general.account_currency_defaults')) !!};
    $('#account-select').on('change', function() {
        if (this.value in accountCurrencyDefaults) {
            var $select = $('#account_currency-select').selectize();
            var selectize = $select[0].selectize;
            selectize.setValue(accountCurrencyDefaults[this.value]);
        }
    });
});
</script>


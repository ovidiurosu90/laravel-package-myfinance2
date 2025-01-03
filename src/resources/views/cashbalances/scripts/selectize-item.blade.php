<script type="module">
$(document).ready(function ()
{
    var $accountSelect = $("#account-select").selectize({
        placeholder: ' {{ trans('myfinance2::cashbalances.forms.item-form.'
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

    var currencyChange = function()
    {
        var selectedOption = $("#account-select option:selected").text().trim();
        var currencyMatchArray = selectedOption.match(/\(([^\)]+)\)/);

        if (currencyMatchArray[1]) { // 0: ($); 1: $
            $('#account_currency-label-tooltip').html(currencyMatchArray[1]);
        }
    };
});
</script>


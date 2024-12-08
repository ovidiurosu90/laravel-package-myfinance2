<script type="module">
$(document).ready(function()
{
    $("#currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::accounts.forms.item-form.currency.'
                                . 'placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true
    });
});
</script>


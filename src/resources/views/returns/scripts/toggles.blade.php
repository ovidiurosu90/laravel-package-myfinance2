<script type="module">
$(document).ready(function()
{
    if ($.fn.bootstrapToggle) {
        $('#toggle-dw-select').bootstrapToggle();
        $('#toggle-cash-select').bootstrapToggle();
    }

    $('#toggle-dw-select').change(function()
    {
        var dwOn = $(this).prop('checked');
        var url = new URL(window.location);
        if (dwOn) {
            url.searchParams.delete('exclude_deposits_withdrawals');
        } else {
            url.searchParams.set('exclude_deposits_withdrawals', '1');
        }
        window.location.href = url.toString();
    });

    $('#toggle-cash-select').change(function()
    {
        var cashOn = $(this).prop('checked');
        var url = new URL(window.location);
        if (cashOn) {
            url.searchParams.delete('exclude_cash');
        } else {
            url.searchParams.set('exclude_cash', '1');
        }
        window.location.href = url.toString();
    });
});
</script>

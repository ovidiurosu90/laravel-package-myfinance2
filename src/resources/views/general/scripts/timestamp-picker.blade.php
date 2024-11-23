<script type="module">
$(document).ready(function ()
{
    /*
    $('#timestamp-picker').datetimepicker({
        format: 'YYYY-MM-DD HH:mm:ss',
        buttons: {showToday: true}
    });
    $('input[data-bs-target="#timestamp-picker"]').attr("placeholder", "Pick timestamp");
    */

    window.timestampPicker1 = new TempusDominus(
        document.getElementById('timestamp-picker'),
        {
            localization: {format: 'yyyy-MM-dd HH:mm:ss'},
            display: {buttons: {today: true}},
        }
    );
    /*
    $('#timestamp-picker').tempusDominus({
        localization: {format: 'yyyy-MM-dd HH:mm:ss'},
        display: {buttons: {today: true}}
    });
    */
    $('input[data-td-target="#timestamp-picker"]').attr("placeholder", "Pick timestamp");
});

</script>


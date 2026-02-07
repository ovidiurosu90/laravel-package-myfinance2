<script type="module">
$(document).ready(function ()
{
    window.timestampPicker1 = new TempusDominus(
        document.getElementById('timestamp-picker'),
        {
            localization: {format: 'yyyy-MM-dd HH:mm:ss', hourCycle: 'h23'},
            display: {buttons: {today: true}},
        }
    );
    $('input[data-td-target="#timestamp-picker"]').attr("placeholder", "Pick timestamp");
});

</script>

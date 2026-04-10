<script type="module">
$(document).ready(function ()
{
    window.expiresAtPicker = new TempusDominus(
        document.getElementById('expires-at-picker'),
        {
            localization: {format: 'yyyy-MM-dd HH:mm:ss', hourCycle: 'h23'},
            display: {buttons: {today: true}},
        }
    );
    $('input[data-td-target="#expires-at-picker"]').attr('placeholder', 'Pick expiry date');
});
</script>

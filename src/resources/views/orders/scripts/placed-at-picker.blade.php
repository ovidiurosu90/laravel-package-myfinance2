<script type="module">
$(document).ready(function ()
{
    window.placedAtPicker = new TempusDominus(
        document.getElementById('placed-at-picker'),
        {
            localization: { format: 'yyyy-MM-dd HH:mm:ss', hourCycle: 'h23' },
            display: { buttons: { today: true } },
        }
    );
    $('input[data-td-target="#placed-at-picker"]').attr('placeholder', 'Pick placed at date');
});
</script>

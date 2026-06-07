<script type="module">
$(document).ready(function ()
{
    const el = document.getElementById('peak-until-picker');
    if (!el) { return; }

    const input   = el.querySelector('input[data-td-target="#peak-until-picker"]');
    const minAttr = input ? input.getAttribute('data-min-date') : null;
    const minDate = minAttr ? new Date(minAttr + 'T00:00:00') : undefined;

    window.peakUntilPicker = new TempusDominus(el, {
        localization: {format: 'yyyy-MM-dd'},
        display: {
            buttons: {clear: true, close: true},
            components: {clock: false},
        },
        restrictions: minDate ? {minDate: minDate} : {},
    });

    $(input).attr('placeholder', 'Permanent');
});
</script>

<script type="module">
$(document).ready(function ()
{
    var is_touch_device = 'ontouchstart' in document.documentElement;
    if (!is_touch_device) {
        $('[data-bs-toggle="tooltip"]').tooltip();
    }
});
</script>


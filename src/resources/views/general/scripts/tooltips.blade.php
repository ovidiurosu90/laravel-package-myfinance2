{{-- Bootstrap tooltips on every [data-bs-toggle="tooltip"] (none on touch devices).
     'lazy' => true: one delegated listener on the body instead, each tooltip created on its first hover or focus. For
     pages with thousands of them (the watchlist: ~5,900, ~600ms of blocking set-up before the list's DataTables could
     run, while the rows kept moving). Not the default: scripts that change a tooltip's text through
     data-bs-original-title (the finance and estimate buttons on the forms) need the tooltip created already. --}}
<script type="module">
$(document).ready(function ()
{
    var is_touch_device = 'ontouchstart' in document.documentElement;
    if (is_touch_device) {
        return;
    }
@if (!empty($lazy))
    $('body').tooltip({ selector: '[data-bs-toggle="tooltip"]' });
@else
    $('[data-bs-toggle="tooltip"]').tooltip();
@endif
});
</script>

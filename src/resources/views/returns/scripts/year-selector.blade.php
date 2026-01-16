<script type="module">
$(document).ready(function()
{
    // Handle year selector change
    $('#year-selector').change(function()
    {
        var year = $(this).val();
        var url = new URL(window.location);
        url.searchParams.set('year', year);
        window.location.href = url.toString();
    });
});
</script>


{{-- Failsafe for the first-paint hiding of a list's Active view (prefilter-active, general/partials/styles): the
     list's script removes the class once DataTables filters. Should that script fail, the rows come back (unfiltered)
     a moment after the page loaded instead of staying hidden; with the script working this is a no-op. Plain script,
     not a module, so it does not depend on the bundle. Included right after such a table. --}}
<script>
window.addEventListener('load', function ()
{
    setTimeout(function ()
    {
        document.querySelectorAll('.data-table.prefilter-active').forEach(function (table)
        {
            table.classList.remove('prefilter-active');
        });
    }, 1000);
});
</script>

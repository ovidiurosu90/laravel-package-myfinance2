<script type="module">
$(document).ready(function()
{
    $('.watchlist-symbol-items-table.data-table').DataTable({
        'pageLength': 100,
        "order": [[ 8, "desc" ]],
        'aoColumnDefs': [{
            'bSortable': false,
            'searchable': false,
            'aTargets': ['no-search'],
            'bTargets': ['no-sort']
        }],
    });
});
</script>


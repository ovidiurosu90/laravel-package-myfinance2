<script type="module">
$(document).ready(function()
{
    $('.cash-balance-items-table.data-table').DataTable({
        'pageLength': 100,
        "order": [[ 1, "desc" ]],
        'aoColumnDefs': [{
            'bSortable': false,
            'searchable': false,
            'aTargets': ['no-search'],
            'bTargets': ['no-sort']
        }]
    });
});
</script>


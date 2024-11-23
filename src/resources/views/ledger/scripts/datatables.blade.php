<script type="module">
$(document).ready(function()
{
    $('.transaction-items-table.data-table').DataTable({
        'pageLength': 100,
        "order": [[ 2, "desc" ]],
        'aoColumnDefs': [{
            'bSortable': false,
            'searchable': false,
            'aTargets': ['no-search'],
            'bTargets': ['no-sort']
        }]
    });
});
</script>


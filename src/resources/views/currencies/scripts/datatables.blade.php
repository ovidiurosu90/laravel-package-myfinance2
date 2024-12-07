<script type="module">
$(document).ready(function()
{
    $('.currency-items-table.data-table').DataTable({
        'pageLength': 100,
        "order": [[ 0, "asc" ]],
        'aoColumnDefs': [{
            'bSortable': false,
            'searchable': false,
            'aTargets': ['no-search'],
            'bTargets': ['no-sort']
        }]
    });
});
</script>


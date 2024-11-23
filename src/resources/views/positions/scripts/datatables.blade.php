<script type="module">
$(document).ready(function()
{
    $('.positions-dashboard-items-table.data-table').DataTable({
        'pageLength': 100,
        'order': [[0, 'asc']],
        'aoColumnDefs': [{
            'bSortable': false,
            'searchable': false,
            'aTargets': ['no-search'],
            'bTargets': ['no-sort']
        }],
    });
});
</script>


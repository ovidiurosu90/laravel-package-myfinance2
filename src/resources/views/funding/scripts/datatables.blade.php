<script type="module">
$(document).ready(function()
{
    $('.funding-dashboard-items-table.data-table').DataTable({
        lengthMenu: [
            [50, 100, 200, -1],
            [50, 100, 200, 'All']
        ],
        'pageLength': -1,
        'ordering': false,
        'aoColumnDefs': [{
            'bSortable': false,
            'searchable': false,
            'aTargets': ['no-search'],
            'bTargets': ['no-sort']
        }]
    });
});
</script>


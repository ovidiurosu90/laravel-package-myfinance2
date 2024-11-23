<script type="module">
$(document).ready(function()
{
    $('.funding-dashboard-items-table.data-table').DataTable({
        'pageLength': 100,
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


<script type="module">
$(document).ready(function()
{
    $('.positions-dashboard-items-table.data-table').DataTable({
        'pageLength': 100,
        'order': [[0, 'asc']],
        'columnDefs': [
            { targets: 'no-sort', sortable: false},
            { targets: 'no-search', searchable: false}
        ]
    });
});
</script>


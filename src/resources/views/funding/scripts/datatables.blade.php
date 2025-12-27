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
        'columnDefs': [
            { targets: 'no-sort', sortable: false},
            { targets: 'no-search', searchable: false}
        ]
    });
});
</script>


<script type="module">
$(document).ready(function()
{
    $('.positions-dashboard-items-table.data-table').DataTable({
        paging: false,
        info: false,
        layout: {
            topStart: null,
            topEnd: 'search',
            bottomStart: null,
            bottomEnd: null,
        },
        order: [[0, 'asc']],
        columnDefs: [
            { targets: 'no-sort', sortable: false },
            { targets: 'no-search', searchable: false },
        ],
    });
});
</script>


<script type="module">
$(document).ready(function()
{
    $('.transaction-items-table.data-table').DataTable({
        'pageLength': 100,
        'order': [[ 2, 'desc' ]],
        'columnDefs': [
            { targets: 'no-sort', sortable: false},
            { targets: 'no-search', searchable: false}
        ]
    });
});
</script>


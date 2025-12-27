<script type="module">
$(document).ready(function()
{
    $('.dividend-items-table.data-table').DataTable({
        'pageLength': 100,
        'order': [[ 1, 'desc' ]],
        'columnDefs': [
            { targets: 'no-sort', sortable: false},
            { targets: 'no-search', searchable: false}
        ]
    });
});
</script>


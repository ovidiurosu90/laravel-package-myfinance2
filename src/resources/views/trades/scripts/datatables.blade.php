<script type="module">
$(document).ready(function()
{
    $('.trade-items-table.data-table').DataTable({
        'pageLength': 100,
        'order': [[ 1, 'desc' ]],
        'columnDefs': [
            { targets: 'no-sort', sortable: false},
            { targets: 'no-search', searchable: false}
        ],
        initComplete: @include('myfinance2::general.scripts.partials.datatable-footer-search')
    });
});
</script>


<script type="module">
$(document).ready(function()
{
    const STATUS_COLUMN = 1;
    const ACTIVE_FILTER = 'DRAFT|PLACED';

    const table = $('.order-items-table.data-table').DataTable({
        'pageLength': 100,
        'order': [[ STATUS_COLUMN, 'desc' ]],
        'autoWidth': false,
        'dom': '<"d-flex align-items-center gap-3 mb-2"l<"ms-auto"f>>r<"table-responsive"t>ip',
        'columnDefs': [
            { targets: 'no-sort', sortable: false },
            { targets: 'no-search', searchable: false }
        ],
        initComplete: @include('myfinance2::general.scripts.partials.datatable-footer-search')
    });

    function applyViewFilter(view, updateUrl)
    {
        if (view === 'active') {
            table.column(STATUS_COLUMN).search(ACTIVE_FILTER, true, false).draw();
        } else {
            table.column(STATUS_COLUMN).search('', false, false).draw();
        }

        $('#orders-view-toggle button').each(function ()
        {
            const isActive = $(this).data('view') === view;
            $(this).toggleClass('btn-primary', isActive)
                   .toggleClass('btn-outline-secondary', !isActive);
        });

        if (updateUrl) {
            history.replaceState(null, '', window.location.pathname + '?view=' + view);
        }
    }

    // Apply initial filter (no URL update, already set by server)
    applyViewFilter('{{ $view }}', false);

    // Toggle click handler
    $('#orders-view-toggle button').on('click', function ()
    {
        applyViewFilter($(this).data('view'), true);
    });
});
</script>

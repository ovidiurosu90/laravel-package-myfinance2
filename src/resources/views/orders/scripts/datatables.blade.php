<script type="module">
$(document).ready(function()
{
    const STATUS_COLUMN = 3;
    const ACTIVE_FILTER = 'DRAFT|PLACED';

    const table = $('.order-items-table.data-table').DataTable({
        'pageLength': 100,
        'order': [[ 1, 'desc' ]],
        'autoWidth': false,
        'columnDefs': [
            { targets: 'no-sort', sortable: false },
            { targets: 'no-search', searchable: false }
        ],
        initComplete: function ()
        {
            this.api()
                .columns()
                .every(function ()
                {
                    let column = this;
                    if (column.footer().innerText == '') {
                        return;
                    }

                    let title = column.footer().textContent;

                    let input = document.createElement('input');
                    input.placeholder = title;
                    input.style.width = '100%';
                    column.footer().replaceChildren(input);

                    input.addEventListener('keyup', () =>
                    {
                        if (column.search() !== this.value) {
                            column.search(input.value, true, false).draw();
                        }
                    });
                });

        }
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

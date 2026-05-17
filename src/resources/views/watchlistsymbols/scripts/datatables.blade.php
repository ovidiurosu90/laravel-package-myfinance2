<script type="module">
$(document).ready(function()
{
    const $tbody = $('.watchlist-symbol-items-table.data-table tbody');

    // On narrow screens wrap the account cards so DataTables measures one-card column width
    let isWide = window.innerWidth >= 1400;
    if (!isWide) {
        $tbody.find('.open-positions-cards').addClass('flex-wrap');
    }

    // Detach performance rows before DataTables init so they are never treated as data rows
    const perfRows = {};
    $tbody.find('tr.performance-row').each(function()
    {
        const sym = $(this).data('symbol');
        perfRows[sym] = $(this).detach();
    });

    // Sorting by performance tags (total gain, % gain, holding period) is not wired yet.
    // To implement: add hidden <th>/<td rowspan="2"> columns to the main data row carrying
    // the numeric values, then add { targets: N, visible: false, searchable: false } entries
    // here. Deferred until the performance section is stable and the displayed metrics are final.
    const openPositionsWidth = '550px';
    const columnDefs = [
        { targets: 'no-sort', sortable: false },
        { targets: 'no-search', searchable: false },
    ];
    if (isWide) {
        columnDefs.push({ targets: 6, width: openPositionsWidth });
    }

    const rowPairs = new Map();

    const dt = $('.watchlist-symbol-items-table.data-table').DataTable({
        'pageLength': 100,
        'order': [[ 5, 'desc' ]],
        'autoWidth': false,
        'columnDefs': columnDefs,
        initComplete: function ()
        {
            this.api()
                .columns()
                .every(function () {
                    let column = this;
                    if (column.footer().textContent == '') {
                        return;
                    }

                    let title = column.footer().textContent;
                    let input = document.createElement('input');
                    input.placeholder = title;
                    column.footer().replaceChildren(input);

                    input.addEventListener('keyup', () => {
                        if (column.search() !== input.value) {
                            column.search(input.value, true, false).draw();
                        }
                    });
                });
        },
        drawCallback: function()
        {
            rowPairs.clear();
            $tbody.find('tr.performance-row').detach();

            $tbody.find('tr').each(function()
            {
                const sym = $(this).data('symbol');
                if (sym && perfRows[sym]) {
                    $(this).after(perfRows[sym]);
                    perfRows[sym].toggleClass('table-info', $(this).hasClass('table-info'));
                    rowPairs.set(this, perfRows[sym][0]);
                    rowPairs.set(perfRows[sym][0], this);
                }
            });
        }
    });

    const nonWatchlistTemplate = document.getElementById('non-watchlist-template');
    if (nonWatchlistTemplate) {
        const $content = $(nonWatchlistTemplate.content.cloneNode(true));
        if (!isWide) {
            $content.find('.open-positions-cards').addClass('flex-wrap');
        }
        $content.appendTo('.watchlist-symbol-items-table');
        nonWatchlistTemplate.remove();
    }

    let resizeTimer;
    $(window).on('resize', function()
    {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function()
        {
            const nowWide = window.innerWidth >= 1400;
            if (nowWide === isWide) return;
            isWide = nowWide;
            $tbody.find('.open-positions-cards').toggleClass('flex-wrap', !isWide);
            $(dt.column(6).header()).css('width', isWide ? openPositionsWidth : '');
            dt.columns.adjust();
        }, 200);
    });

    $tbody.on('mouseenter', 'tr', function()
    {
        $(this).addClass('dt-row-hover');
        const partner = rowPairs.get(this);
        if (partner) {
            $(partner).addClass('dt-row-hover');
        }
    }).on('mouseleave', 'tr', function()
    {
        $(this).removeClass('dt-row-hover');
        const partner = rowPairs.get(this);
        if (partner) {
            $(partner).removeClass('dt-row-hover');
        }
    });
});
</script>

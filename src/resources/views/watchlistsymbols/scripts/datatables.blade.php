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

    const openPositionsWidth = '550px';
    const columnDefs = [
        { targets: 'no-sort', sortable: false },
        { targets: 'no-search', searchable: false },
        { targets: 11, visible: false, searchable: false, type: 'num' },
        { targets: 12, visible: false, searchable: false, type: 'num' },
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
        initComplete: @include('myfinance2::general.scripts.partials.datatable-footer-search'),
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

    // ── Overall gain/y sort controls (€ and %) ──
    const GAIN_Y_EUR_COL = 11;
    const GAIN_Y_PCT_COL = 12;
    let pinnedGainYCol = null;
    let pinnedGainYDir = null;
    let reorderingForGainY = false;

    function makeGainYSelect(id)
    {
        return $('<select>', { class: 'form-select form-select-sm', id, style: 'width: auto' })
            .append('<option value="">off</option>')
            .append('<option value="desc">best → worst</option>')
            .append('<option value="asc">worst → best</option>');
    }
    const $gainYEurSelect = makeGainYSelect('gain-y-eur-sort-dir');
    const $gainYPctSelect = makeGainYSelect('gain-y-pct-sort-dir');

    $('<div>', { class: 'd-flex align-items-center gap-1 ms-3' })
        .append($('<label>', { for: 'gain-y-eur-sort-dir', class: 'mb-0 text-nowrap' }).text('Gain/y €:'))
        .append($gainYEurSelect)
        .append($('<label>', { for: 'gain-y-pct-sort-dir', class: 'mb-0 text-nowrap ms-2' }).text('Gain/y %:'))
        .append($gainYPctSelect)
        .insertAfter($(dt.table().container()).find('.dt-length'));

    function applyGainYSort(col, dir)
    {
        pinnedGainYCol = dir ? col : null;
        pinnedGainYDir = dir || null;
        const current = dt.order();
        const filtered = current.filter(o => o[0] !== GAIN_Y_EUR_COL && o[0] !== GAIN_Y_PCT_COL);
        const newOrder = pinnedGainYCol
            ? [[pinnedGainYCol, pinnedGainYDir], ...filtered]
            : (filtered.length ? filtered : [[5, 'desc']]);
        dt.order(newOrder).draw(false);
    }

    $gainYEurSelect.on('change', function()
    {
        $gainYPctSelect.val('');
        applyGainYSort(GAIN_Y_EUR_COL, $(this).val() || null);
    });

    $gainYPctSelect.on('change', function()
    {
        $gainYEurSelect.val('');
        applyGainYSort(GAIN_Y_PCT_COL, $(this).val() || null);
    });

    $(dt.table().node()).on('order.dt', function()
    {
        if (!pinnedGainYCol || reorderingForGainY) return;
        const current = dt.order();
        if (!current.length || current[0][0] !== pinnedGainYCol) {
            const filtered = current.filter(o => o[0] !== GAIN_Y_EUR_COL && o[0] !== GAIN_Y_PCT_COL);
            reorderingForGainY = true;
            dt.order([[pinnedGainYCol, pinnedGainYDir], ...filtered]).draw(false);
            reorderingForGainY = false;
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

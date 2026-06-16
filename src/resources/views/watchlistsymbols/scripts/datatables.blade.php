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

    // Column indices (0-based): 0=Symbol,1=Price,2=DayChg,3=52W,4=%Low,5=%High,
    // 6=OpenPositions,7=Orders,8=Alerts,9=Actions (Edit + Delete stacked),
    // 10=hidden GainY EUR,11=hidden GainY PCT
    // Tier and quadrant filter data live on <tr data-tier data-quadrant> attributes.
    const columnDefs = [
        { targets: 'no-sort', sortable: false },
        { targets: 'no-search', searchable: false },
        { targets: 10, visible: false, searchable: false, type: 'num' },
        { targets: 11, visible: false, searchable: false, type: 'num' },
    ];
    if (isWide) {
        columnDefs.push({ targets: 6, width: openPositionsWidth });
    }

    const rowPairs = new Map();

    // ── Helpers ──
    // values may be plain strings (value === label) or {value, label} objects.
    function buildFilterSelect(placeholder, id, values)
    {
        const $sel = $('<select>', { class: 'form-select form-select-sm', id, style: 'width:auto' });
        $sel.append($('<option>', { value: '', text: placeholder }));
        values.forEach(function(v)
        {
            const isObj = (typeof v === 'object');
            $sel.append($('<option>', { value: isObj ? v.value : v, text: isObj ? v.label : v }));
        });
        return $sel;
    }

    function makeGainYSelect(id)
    {
        return $('<select>', { class: 'form-select form-select-sm', id, style: 'width: auto' })
            .append('<option value="">off</option>')
            .append('<option value="desc">best → worst</option>')
            .append('<option value="asc">worst → best</option>');
    }

    // ── Build all controls before DataTable init ──
    const $gainYEurSelect = makeGainYSelect('gain-y-eur-sort-dir');
    const $gainYPctSelect = makeGainYSelect('gain-y-pct-sort-dir');

    const tierValues     = ['Platinum', 'Gold', 'Silver', 'Bronze', 'Rust'];
    const quadrantValues = ['Steady grower', 'Volatile winner', 'Dead weight', 'Danger zone'];
    // Each action option covers the owned and unowned variants for the same quadrant.
    // The pipe-separated value is split in ext.search to match either action.
    const actionValues   = [
        { value: 'ACCUMULATE',  label: 'ACCUMULATE' },
        { value: 'HOLD|WATCH',  label: 'HOLD | WATCH' },
        { value: 'REDUCE|SKIP', label: 'REDUCE | SKIP' },
        { value: 'EXIT|AVOID',  label: 'EXIT | AVOID' },
    ];

    const $tierFilter     = buildFilterSelect('All tiers',     'tier-filter',     tierValues);
    const $quadrantFilter = buildFilterSelect('All quadrants', 'quadrant-filter', quadrantValues);
    const $actionFilter   = buildFilterSelect('All actions',   'action-filter',   actionValues);

    const dt = $('.watchlist-symbol-items-table.data-table').DataTable({
        'paging': false,
        'lengthChange': false,
        'dom': '<"dt-toolbar d-flex align-items-center px-2 py-2 border-bottom bg-body-tertiary"'
             + '<"sort-filter-placeholder d-flex align-items-center flex-wrap gap-2 flex-grow-1">'
             + '<"ms-auto flex-shrink-0"f>>rt',
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

    // ── Populate combined sort + filter toolbar ──
    const $bar = $(dt.table().container()).find('.sort-filter-placeholder');

    $('<span>', { class: 'text-muted small fw-semibold text-uppercase' }).text('Sort')
        .appendTo($bar);
    $('<div>', { class: 'd-flex align-items-center gap-1' })
        .append($('<label>', { for: 'gain-y-eur-sort-dir', class: 'mb-0 small text-nowrap' }).text('Gain/y €'))
        .append($gainYEurSelect)
        .appendTo($bar);
    $('<div>', { class: 'd-flex align-items-center gap-1' })
        .append($('<label>', { for: 'gain-y-pct-sort-dir', class: 'mb-0 small text-nowrap' }).text('Gain/y %'))
        .append($gainYPctSelect)
        .appendTo($bar);

    $('<span>', { class: 'text-muted small fw-semibold text-uppercase' }).text('Filter')
        .appendTo($bar);
    $bar.append($tierFilter).append($quadrantFilter).append($actionFilter);

    // ── Style the DataTables search label to match Sort / Filter section headers ──
    const $dtSearch = $(dt.table().container()).find('.dt-search');
    $dtSearch.addClass('d-flex align-items-center gap-1');
    $dtSearch.find('label').contents().filter(function() { return this.nodeType === 3; }).remove();
    // Drop DataTables' default .5em input margin so only the gap-1 spacing applies (halves the SEARCH/input gap)
    $dtSearch.find('input').css('margin-left', 0);
    $dtSearch.prepend($('<span>', { class: 'text-muted small fw-semibold text-uppercase text-nowrap' }).text('Search'));

    // ── Filter search logic ──
    // Tier, quadrant, and action values are stored as data-* attributes on the <tr>
    // rather than in hidden columns, so they are reliably accessible regardless of
    // DataTables' searchable-column data array behaviour.
    // Quadrant and action use JSON arrays covering all time horizons (3m/6m/1y/2y +
    // overall), so the filter matches if the selected value appears in ANY horizon.
    $.fn.dataTable.ext.search.push(function(settings, searchData, dataIndex)
    {
        const tierVal     = $tierFilter.val();
        const quadrantVal = $quadrantFilter.val().toLowerCase();
        const actionVal   = $actionFilter.val(); // e.g. "" | "ACCUMULATE" | "HOLD|WATCH"

        if (!tierVal && !quadrantVal && !actionVal) return true;

        const $row      = $(dt.row(dataIndex).node());
        const tier      = ($row.data('tier') || '').toLowerCase();
        const quadrants = $row.data('quadrants') || [];
        const actions   = $row.data('actions')   || [];

        if (tierVal     && !tier.includes(tierVal.toLowerCase()))                 return false;
        if (quadrantVal && !quadrants.some(q => q.toLowerCase() === quadrantVal)) return false;
        if (actionVal) {
            // Split pipe-separated pair (e.g. "HOLD|WATCH") and match either variant.
            const actionParts = actionVal.split('|');
            if (!actions.some(a => actionParts.includes(a))) return false;
        }
        return true;
    });

    $tierFilter.add($quadrantFilter).add($actionFilter).on('change', function()
    {
        dt.draw();
    });

    // ── Gain/y sort controls ──
    const GAIN_Y_EUR_COL = 10;
    const GAIN_Y_PCT_COL = 11;
    let pinnedGainYCol = null;
    let pinnedGainYDir = null;
    let reorderingForGainY = false;

    function applyGainYSort(col, dir)
    {
        pinnedGainYCol = dir ? col : null;
        pinnedGainYDir = dir || null;
        const current  = dt.order();
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

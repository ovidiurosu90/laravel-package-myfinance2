<script type="module">
$(document).ready(function()
{
    // Col 0: checkbox, 1: Status, 2: Id, 3: Symbol, 4: Account(s), 5: Type, 6: Current→Target
    const STATUS_COLUMN = 1;
    const ACTIVE_FILTER = 'ACTIVE';

    const table = $('.alert-items-table.data-table').DataTable({
        'pageLength': 100,
        'order': [[ 3, 'asc' ]],
        'autoWidth': false,
        'dom': '<"d-flex align-items-center gap-2 mb-2 flex-wrap"<"alert-bulk-slot">'
             + '<"alert-filter-slot d-flex align-items-center gap-2 flex-wrap">'
             + '<"ms-auto flex-shrink-0"f>>r<"table-responsive"t>'
             + '<"d-flex align-items-center justify-content-between flex-wrap gap-2 mt-2"l<"d-flex align-items-center gap-3"ip>>',
        'columnDefs': [
            { targets: 'no-sort', sortable: false },
            { targets: 'no-search', searchable: false }
        ],
        initComplete: @include('myfinance2::general.scripts.partials.datatable-footer-search')
    });

    // Move the bulk action bar into the DataTables header row (left of search)
    $('#bulk-action-bar').appendTo('.alert-bulk-slot').css('display', 'flex');

    // ── Style the DataTables search label to match Sort / Filter section headers ──
    const $dtSearch = $(table.table().container()).find('.dt-search');
    $dtSearch.addClass('d-flex align-items-center gap-1');
    $dtSearch.find('label').contents().filter(function() { return this.nodeType === 3; }).remove();
    // Drop DataTables' default .5em input margin so only the gap-1 spacing applies (halves the SEARCH/input gap)
    $dtSearch.find('input').css('margin-left', 0);
    $dtSearch.prepend($('<span>', { class: 'text-muted small fw-semibold text-uppercase text-nowrap' }).text('Search'));

    // ── Type & duration filters ──────────────────────────────────────────────
    // Mirrors the watchlist-symbols toolbar: inline selects backed by data-* on
    // each <tr>, combined with the view toggle / search through ext.search.
    function buildFilterSelect(id, placeholder, options)
    {
        const $sel = $('<select>', { class: 'form-select form-select-sm', id, style: 'width:auto' });
        $sel.append($('<option>', { value: '', text: placeholder }));
        options.forEach(function (o)
        {
            $sel.append($('<option>', { value: o.value, text: o.label }));
        });
        return $sel;
    }

    const $typeFilter = buildFilterSelect('alert-type-filter', 'All types', [
        { value: 'PRICE_ABOVE', label: '▲ Above' },
        { value: 'PRICE_BELOW', label: '▼ Below' },
    ]);
    const $durationFilter = buildFilterSelect('alert-duration-filter', 'All durations', [
        { value: 'temporary', label: 'Temporary (expiring)' },
        { value: 'permanent', label: 'Permanent (no expiry)' },
    ]);

    const $filterBar = $('.alert-filter-slot');
    $('<span>', { class: 'text-muted small fw-semibold text-uppercase' }).text('Filter').appendTo($filterBar);
    $filterBar.append($typeFilter).append($durationFilter);

    $.fn.dataTable.ext.search.push(function (settings, searchData, dataIndex)
    {
        if (settings.nTable !== table.table().node()) { return true; }

        const typeVal     = $typeFilter.val();
        const durationVal = $durationFilter.val();
        if (!typeVal && !durationVal) { return true; }

        const $row = $(table.row(dataIndex).node());

        if (typeVal && $row.data('alert-type') !== typeVal) { return false; }
        if (durationVal) {
            const isTemporary = String($row.data('temporary')) === '1';
            if (durationVal === 'temporary' && !isTemporary) { return false; }
            if (durationVal === 'permanent' &&  isTemporary) { return false; }
        }
        return true;
    });

    $typeFilter.add($durationFilter).on('change', function ()
    {
        clearSelection();
        table.draw();
    });

    // ── Bulk selection ───────────────────────────────────────────────────────
    // Declared before applyViewFilter because clearSelection is called inside it

    const selectedIds = new Set();
    const $bulkBar    = $('#bulk-action-bar');
    const $countLabel = $('#bulk-selection-count');
    const $selectAll  = $('#select-all-alerts');

    function visibleCheckboxes()
    {
        return table.rows({ filter: 'applied' }).nodes().to$().find('.alert-row-checkbox');
    }

    function updateBulkBar()
    {
        const n        = selectedIds.size;
        const disabled = n === 0;
        $countLabel.text(n + ' selected');
        $bulkBar.find('[data-bulk-action]').prop('disabled', disabled);
        $('#bulk-clear-selection').prop('disabled', disabled);
    }

    function clearSelection()
    {
        selectedIds.clear();
        $selectAll.prop('checked', false);
        $('.alert-row-checkbox').prop('checked', false);
        updateBulkBar();
    }

    // ── View filter ──────────────────────────────────────────────────────────

    function applyViewFilter(view, updateUrl)
    {
        clearSelection();

        if (view === 'active') {
            table.column(STATUS_COLUMN).search(ACTIVE_FILTER, true, false).draw();
        } else {
            table.column(STATUS_COLUMN).search('', false, false).draw();
        }

        $('#alerts-view-toggle button').each(function ()
        {
            const isActive = $(this).data('view') === view;
            $(this).toggleClass('btn-primary', isActive)
                   .toggleClass('btn-outline-secondary', !isActive);
        });

        if (updateUrl) {
            history.replaceState(null, '', window.location.pathname + '?view=' + view);
        }
    }

    function applySymbolGrouping()
    {
        // Clean ALL rows (including hidden) to avoid stale decorations after view switches
        table.rows().nodes().toArray().forEach(function (row)
        {
            $(row).removeClass('alert-group-a alert-group-b');
            $(row).find('.symbol-dup-badge, .type-dup-warning').remove();
        });

        const rows = table.rows({ filter: 'applied' }).nodes().toArray();

        const symbolRows = {};
        rows.forEach(function (row)
        {
            const sym = $(row).data('symbol');
            if (!symbolRows[sym]) { symbolRows[sym] = []; }
            symbolRows[sym].push(row);
        });

        let groupIdx = 0;
        Object.values(symbolRows).forEach(function (symRows)
        {
            if (symRows.length <= 1) { return; }

            const groupClass = (groupIdx % 2 === 0) ? 'alert-group-a' : 'alert-group-b';
            groupIdx++;

            const typeCounts = {};
            symRows.forEach(function (row)
            {
                const type = $(row).data('alert-type');
                typeCounts[type] = (typeCounts[type] || 0) + 1;
            });

            symRows.forEach(function (row)
            {
                $(row).addClass(groupClass);

                $(row).find('td').eq(3)
                    .append('<span class="badge bg-secondary ms-1 symbol-dup-badge">' + symRows.length + '×</span>');

                const type = $(row).data('alert-type');
                if (typeCounts[type] > 1) {
                    const $warning = $(
                        '<span class="type-dup-warning text-warning me-1"'
                        + ' data-bs-toggle="tooltip"'
                        + ' title="Duplicate: another ' + type + ' alert exists for this symbol">'
                        + '<i class="fa fa-exclamation-triangle fa-fw" aria-hidden="true"></i></span>'
                    );
                    $(row).find('td').eq(5).prepend($warning);
                    $warning.tooltip();
                }
            });
        });
    }

    applyViewFilter('{{ $view }}', false);
    applySymbolGrouping();

    $('#alerts-view-toggle button').on('click', function ()
    {
        applyViewFilter($(this).data('view'), true);
    });

    // ── Checkbox handlers ────────────────────────────────────────────────────

    // Select-all checkbox in the header
    $selectAll.on('change', function ()
    {
        const checked = this.checked;
        visibleCheckboxes().each(function ()
        {
            this.checked = checked;
            const id = parseInt(this.value, 10);
            if (checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
        });
        updateBulkBar();
    });

    // Individual row checkbox
    $(document).on('change', '.alert-row-checkbox', function ()
    {
        const id = parseInt(this.value, 10);
        if (this.checked) {
            selectedIds.add(id);
        } else {
            selectedIds.delete(id);
            $selectAll.prop('checked', false);
        }

        // Auto-check select-all when every visible row is checked
        const $visible = visibleCheckboxes();
        if ($visible.length > 0 && $visible.filter(':checked').length === $visible.length) {
            $selectAll.prop('checked', true);
        }

        updateBulkBar();
    });

    // Clear button
    $('#bulk-clear-selection').on('click', clearSelection);

    // Reset select-all header checkbox after DataTable redraws
    table.on('draw', function ()
    {
        $selectAll.prop('checked', false);
        applySymbolGrouping();
    });

    // ── Bulk action buttons ──────────────────────────────────────────────────

    $('[data-bulk-action]').on('click', function ()
    {
        const action = $(this).data('bulk-action');
        const ids    = Array.from(selectedIds);

        if (ids.length === 0) { return; }

        const label      = action.charAt(0).toUpperCase() + action.slice(1);
        const confirmMsg = action === 'delete'
            ? `Delete ${ids.length} alert(s)? This cannot be undone.`
            : `${label} ${ids.length} alert(s)?`;

        if (!window.confirm(confirmMsg)) { return; }

        const $form = $('#bulk-action-form');
        $('#bulk-action-input').val(action);
        $form.find('.bulk-id-input').remove();
        ids.forEach(function (id)
        {
            $('<input>').attr({
                type:  'hidden',
                class: 'bulk-id-input',
                name:  'ids[]',
                value: id,
            }).appendTo($form);
        });
        $form.submit();
    });
});
</script>

<script type="module">
$(document).ready(function ()
{
    // Col 0: checkbox, 1: Symbol, 2: Status, 3: Last Alerted, 4: Actions
    const table = $('.peak-prox-table.data-table').DataTable({
        'pageLength': 100,
        'order': [[ 1, 'asc' ]],
        'autoWidth': false,
        'dom': '<"d-flex align-items-center gap-3 mb-2"<"peak-bulk-slot">l<"ms-auto"f>>rtip',
        'columnDefs': [
            { targets: 'no-sort',   sortable:   false },
            { targets: 'no-search', searchable: false },
        ],
        'language': {
            'emptyTable': 'No open positions to alert on.',
        },
    });

    // Move the bulk action bar into the DataTables header row (left of search)
    $('#peak-bulk-action-bar').appendTo('.peak-bulk-slot').css('display', 'flex');

    // ── Bulk selection ────────────────────────────────────────────────────────

    const selectedSymbols = new Set();
    const $selectAll  = $('#select-all-peak');
    const $countLabel = $('#peak-bulk-selection-count');
    const $enableBtn  = $('#peak-bulk-enable-btn');
    const $disableBtn = $('#peak-bulk-disable-btn');
    const $clearBtn   = $('#peak-bulk-clear');

    function visibleCheckboxes()
    {
        return table.rows({ filter: 'applied' }).nodes().to$().find('.peak-row-checkbox');
    }

    function updateBulkBar()
    {
        const n        = selectedSymbols.size;
        const disabled = n === 0;
        $countLabel.text(n + ' selected');
        $enableBtn.prop('disabled', disabled);
        $disableBtn.prop('disabled', disabled);
        $clearBtn.prop('disabled', disabled);
    }

    function clearSelection()
    {
        selectedSymbols.clear();
        $selectAll.prop('checked', false);
        $('.peak-row-checkbox').prop('checked', false);
        updateBulkBar();
    }

    // Select-all checkbox in the header
    $selectAll.on('change', function ()
    {
        const checked = this.checked;
        visibleCheckboxes().each(function ()
        {
            this.checked = checked;
            if (checked) {
                selectedSymbols.add(this.value);
            } else {
                selectedSymbols.delete(this.value);
            }
        });
        updateBulkBar();
    });

    // Individual row checkbox
    $(document).on('change', '.peak-row-checkbox', function ()
    {
        if (this.checked) {
            selectedSymbols.add(this.value);
        } else {
            selectedSymbols.delete(this.value);
            $selectAll.prop('checked', false);
        }

        const $visible = visibleCheckboxes();
        if ($visible.length > 0 && $visible.filter(':checked').length === $visible.length) {
            $selectAll.prop('checked', true);
        }

        updateBulkBar();
    });

    // Clear button
    $clearBtn.on('click', clearSelection);

    // Clear selection after DataTable redraws (search / page change)
    table.on('draw', function ()
    {
        clearSelection();
    });

    // ── Bulk enable / disable ─────────────────────────────────────────────────

    function submitBulk(status)
    {
        const symbols = Array.from(selectedSymbols);
        if (symbols.length === 0) { return; }

        const $form = $('#peak-bulk-action-form');
        const url   = status === 'enable' ? $form.data('enable-url') : $form.data('disable-url');
        $form.attr('action', url);

        $form.find('.bulk-symbol-input').remove();
        symbols.forEach(function (symbol)
        {
            $('<input>').attr({
                type:  'hidden',
                class: 'bulk-symbol-input',
                name:  'symbols[]',
                value: symbol,
            }).appendTo($form);
        });
        $form.submit();
    }

    $enableBtn.on('click', function () { submitBulk('enable'); });
    $disableBtn.on('click', function () { submitBulk('disable'); });
});
</script>

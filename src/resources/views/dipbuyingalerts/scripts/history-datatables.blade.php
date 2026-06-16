<script type="module">
$(document).ready(function ()
{
    // Col 0: checkbox, 1: Sent At, 2: Trigger, 3: Eff. DD, 4: Driver, 5: Target, 6: Deployed,
    // 7: Tranche, 8: Verdict, 9: Status, 10: Actions
    const table = $('.dip-history-table.data-table').DataTable({
        'pageLength': 100,
        'order': [[ 1, 'desc' ]],
        'autoWidth': false,
        'dom': '<"d-flex align-items-center gap-3 mb-2"<"dip-history-bulk-slot">l<"ms-auto"f>>rtip',
        'columnDefs': [
            { targets: 'no-sort',   sortable:   false },
            { targets: 'no-search', searchable: false },
        ],
        'language': {
            'emptyTable': 'No alert history found.',
        },
    });

    // Move the bulk action bar into the DataTables header row (left of search).
    $('#dip-history-bulk-action-bar').appendTo('.dip-history-bulk-slot').css('display', 'flex');

    // ── Bulk selection ────────────────────────────────────────────────────────

    const selectedIds = new Set();
    const $selectAll  = $('#select-all-dip-history');
    const $countLabel = $('#dip-history-bulk-selection-count');
    const $deleteBtn  = $('#dip-history-bulk-delete-btn');

    function visibleCheckboxes()
    {
        return table.rows({ filter: 'applied' }).nodes().to$().find('.dip-history-row-checkbox');
    }

    function updateBulkBar()
    {
        const n = selectedIds.size;
        $countLabel.text(n + ' selected');
        $deleteBtn.prop('disabled', n === 0);
    }

    function clearSelection()
    {
        selectedIds.clear();
        $selectAll.prop('checked', false);
        $('.dip-history-row-checkbox').prop('checked', false);
        updateBulkBar();
    }

    // Select-all checkbox in the header: toggles every visible (filtered) row.
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

    // Individual row checkbox.
    $(document).on('change', '.dip-history-row-checkbox', function ()
    {
        const id = parseInt(this.value, 10);
        if (this.checked) {
            selectedIds.add(id);
        } else {
            selectedIds.delete(id);
            $selectAll.prop('checked', false);
        }

        const $visible = visibleCheckboxes();
        if ($visible.length > 0 && $visible.filter(':checked').length === $visible.length) {
            $selectAll.prop('checked', true);
        }

        updateBulkBar();
    });

    // Clear selection after the DataTable redraws (search / filter / page change), since the
    // off-screen checkboxes leave the DOM and the visible set changes.
    table.on('draw', function ()
    {
        clearSelection();
    });

    // Delete button: inject the selected ids as hidden inputs, confirm, submit.
    $deleteBtn.on('click', function ()
    {
        const ids = Array.from(selectedIds);
        if (ids.length === 0) { return; }

        if (!window.confirm(`Delete ${ids.length} notification record(s)? This cannot be undone.`)) {
            return;
        }

        const $form = $('#dip-history-bulk-action-form');
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

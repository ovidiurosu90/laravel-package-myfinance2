<script type="module">
$(document).ready(function ()
{
    // Col 0: checkbox, 1: Sent At, 2: Symbol, 3: Price, 4: Windows, 5: Closest, 6: Peak Dates,
    // 7: Status, 8: Actions
    const table = $('.peak-history-table.data-table').DataTable({
        'pageLength': 100,
        'order': [[ 1, 'desc' ]],
        'autoWidth': false,
        'dom': '<"d-flex align-items-center gap-3 mb-2"<"peak-history-bulk-slot">l<"ms-auto"f>>rtip',
        'columnDefs': [
            { targets: 'no-sort',   sortable:   false },
            { targets: 'no-search', searchable: false },
        ],
        'language': {
            'emptyTable': 'No notification history found.',
        },
    });

    // Move the bulk action bar into the DataTables header row (left of search)
    $('#peak-history-bulk-action-bar').appendTo('.peak-history-bulk-slot').css('display', 'flex');

    // ── Bulk selection ────────────────────────────────────────────────────────

    const selectedIds = new Set();
    const $selectAll  = $('#select-all-peak-history');
    const $countLabel = $('#peak-history-bulk-selection-count');
    const $deleteBtn  = $('#peak-history-bulk-delete-btn');
    const $clearBtn   = $('#peak-history-bulk-clear');

    function visibleCheckboxes()
    {
        return table.rows({ filter: 'applied' }).nodes().to$().find('.peak-history-row-checkbox');
    }

    function updateBulkBar()
    {
        const n        = selectedIds.size;
        const disabled = n === 0;
        $countLabel.text(n + ' selected');
        $deleteBtn.prop('disabled', disabled);
        $clearBtn.prop('disabled', disabled);
    }

    function clearSelection()
    {
        selectedIds.clear();
        $selectAll.prop('checked', false);
        $('.peak-history-row-checkbox').prop('checked', false);
        updateBulkBar();
    }

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
    $(document).on('change', '.peak-history-row-checkbox', function ()
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

    // Clear button
    $clearBtn.on('click', clearSelection);

    // Clear selection after DataTable redraws (search / filter / page change)
    table.on('draw', function ()
    {
        clearSelection();
    });

    // Delete button
    $deleteBtn.on('click', function ()
    {
        const ids = Array.from(selectedIds);
        if (ids.length === 0) { return; }

        if (!window.confirm(`Delete ${ids.length} notification record(s)? This cannot be undone.`)) {
            return;
        }

        const $form = $('#peak-history-bulk-action-form');
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

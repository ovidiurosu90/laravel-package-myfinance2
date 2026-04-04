<script type="module">
$(document).ready(function()
{
    // Col 0: checkbox, 1: Status, 2: Id, 3: Symbol, 4: Account(s), 5: Type, 6: Current→Target
    const STATUS_COLUMN = 1;
    const ACTIVE_FILTER = 'ACTIVE';

    const table = $('.alert-items-table.data-table').DataTable({
        'pageLength': 100,
        'order': [[ 3, 'asc' ]],
        'dom': '<"d-flex align-items-center gap-3 mb-2"<"alert-bulk-slot">l<"ms-auto"f>>rtip',
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

    // Move the bulk action bar into the DataTables header row (left of search)
    $('#bulk-action-bar').appendTo('.alert-bulk-slot').css('display', 'flex');

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

    applyViewFilter('{{ $view }}', false);

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

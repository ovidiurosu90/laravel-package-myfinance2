<script type="module">
$(document).ready(function()
{
    $('#revertSplitModal').on('show.bs.modal', function (event)
    {
        const modal     = $(this);
        const btn       = $(event.relatedTarget);
        const origTrades = parseInt(btn.data('trades-updated'), 10);
        const origAlerts = parseInt(btn.data('alerts-adjusted'), 10);

        modal.find('#revert-modal-symbol').text(btn.data('split-symbol'));
        modal.find('#revert-modal-ratio').text(btn.data('split-ratio'));
        modal.find('#revert-modal-date').text(btn.data('split-date'));
        modal.find('#revert-modal-trades').text(origTrades);
        modal.find('#revert-modal-alerts').text(origAlerts);
        modal.find('#revert-split-form').attr('action', btn.data('revert-url'));

        const $loading = modal.find('#revert-preview-loading');
        const $result  = modal.find('#revert-preview-result');
        $loading.removeClass('d-none');
        $result.addClass('d-none').html('');

        $.get(btn.data('preview-url'))
            .done(function (data)
            {
                $loading.addClass('d-none');

                const tFound = data.trades_found;
                const aFound = data.alerts_found;
                const isComplete = (tFound === origTrades) && (aFound === origAlerts);
                let html;

                if (isComplete) {
                    html = '<span class="text-success">'
                        + '<i class="fa fa-check-circle fa-fw" aria-hidden="true"></i> '
                        + 'Complete revert — found all ' + tFound + ' trade(s) and ' + aFound + ' alert(s).'
                        + '</span>';
                } else {
                    html = '<span class="text-warning">'
                        + '<i class="fa fa-exclamation-triangle fa-fw" aria-hidden="true"></i> '
                        + 'Partial revert — found ' + tFound + '/' + origTrades + ' trade(s) and '
                        + aFound + '/' + origAlerts + ' alert(s).'
                        + '</span>';
                }

                $result.html(html).removeClass('d-none');
            })
            .fail(function ()
            {
                $loading.addClass('d-none');
                $result.html(
                    '<span class="text-danger">'
                    + '<i class="fa fa-times-circle fa-fw" aria-hidden="true"></i> '
                    + 'Could not check current records.'
                    + '</span>'
                ).removeClass('d-none');
            });
    });

    $('#reapplySplitModal').on('show.bs.modal', function (event)
    {
        const btn = $(event.relatedTarget);
        $(this).find('#reapply-modal-symbol').text(btn.data('split-symbol'));
        $(this).find('#reapply-modal-ratio').text(btn.data('split-ratio'));
        $(this).find('#reapply-modal-date').text(btn.data('split-date'));
        $(this).find('#reapply-split-form').attr('action', btn.data('reapply-url'));
    });

    const table = $('.split-items-table.data-table').DataTable({
        'pageLength': 50,
        'order': [[ 2, 'desc' ]],
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
});
</script>

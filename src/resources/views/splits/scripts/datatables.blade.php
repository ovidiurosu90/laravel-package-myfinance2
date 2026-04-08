<script type="module">
$(document).ready(function()
{
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

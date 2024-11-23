<script type="module">
$(document).ready(function()
{
    $('.trade-items-table.data-table').DataTable({
        'pageLength': 100,
        "order": [[ 1, "desc" ]],
        'aoColumnDefs': [{
            'bSortable': false,
            'searchable': false,
            'aTargets': ['no-search'],
            'bTargets': ['no-sort']
        }],
        initComplete: function () {
            this.api()
                .columns()
                .every(function () {
                    let column = this;
                    if (column.footer().innerText == '') {
                        return
                    }

                    let title = column.footer().textContent;

                    // Create input element
                    let input = document.createElement('input');
                    input.placeholder = title;
                    column.footer().replaceChildren(input);

                    // Event listener for user input
                    input.addEventListener('keyup', () => {
                        if (column.search() !== this.value) {
                            column.search(input.value, true, false).draw();
                        }
                    });
            });
        }
    });
});
</script>


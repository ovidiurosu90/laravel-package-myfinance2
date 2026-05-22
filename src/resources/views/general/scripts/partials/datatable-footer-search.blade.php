function ()
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
                if (column.search() !== input.value) {
                    column.search(input.value, true, false).draw();
                }
            });
        });
}

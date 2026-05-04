<script type="module">
    const table = document.getElementById('gainsPerYearTable');
    if (table) {
        table.addEventListener('click', (e) => {
            const yearRow = e.target.closest('.gpy-year');
            const accountRow = e.target.closest('.gpy-account');

            if (accountRow) {
                const accountKey = accountRow
                    .dataset.gpyAccount;
                const chevron = accountRow
                    .querySelector('.gpy-chevron');
                const children = table.querySelectorAll(
                    `.gpy-account-child[data-gpy-parent-account`
                    + `="${accountKey}"]`
                );
                const isExpanded = chevron.style.transform
                    === 'rotate(90deg)';

                chevron.style.transform = isExpanded
                    ? '' : 'rotate(90deg)';
                children.forEach((child) => {
                    child.style.display = isExpanded
                        ? 'none' : '';
                });
                return;
            }

            if (yearRow) {
                const year = yearRow.dataset.gpyYear;
                const chevron = yearRow
                    .querySelector('.gpy-chevron');
                const children = table.querySelectorAll(
                    `.gpy-year-child[data-gpy-parent-year`
                    + `="${year}"]`
                );
                const isExpanded = chevron.style.transform
                    === 'rotate(90deg)';

                chevron.style.transform = isExpanded
                    ? '' : 'rotate(90deg)';

                if (isExpanded) {
                    children.forEach((child) => {
                        child.style.display = 'none';
                        const accChevron = child
                            .querySelector('.gpy-chevron');
                        if (accChevron) {
                            accChevron.style.transform = '';
                        }
                    });
                    const symChildren = table.querySelectorAll(
                        `.gpy-account-child`
                        + `[data-gpy-parent-account^="${year}-"]`
                    );
                    symChildren.forEach((child) => {
                        child.style.display = 'none';
                    });
                } else {
                    children.forEach((child) => {
                        child.style.display = '';
                    });
                }
            }
        });
    }
</script>

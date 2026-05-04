<script type="module">
    const header = document.querySelector(
        '[data-bs-target="#otherAccountsBody"]'
    );
    if (header) {
        const chevron = header.querySelector('.other-acc-chevron');
        const collapseEl = document.getElementById(
            'otherAccountsBody'
        );
        collapseEl.addEventListener('show.bs.collapse', () => {
            chevron.style.transform = 'rotate(90deg)';
        });
        collapseEl.addEventListener('hide.bs.collapse', () => {
            chevron.style.transform = '';
        });
    }
</script>

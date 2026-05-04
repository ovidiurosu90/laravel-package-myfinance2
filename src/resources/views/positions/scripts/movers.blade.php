<script type="module">
    const moversEl = document.getElementById('biggest-movers');
    const moversTitleEl = document.getElementById('biggest-movers-title');
    const iconEl = moversTitleEl?.querySelector('i');
    const summaryEl = document.getElementById('movers-summary');

    // Restore saved state (default: collapsed)
    if (localStorage.getItem('movers-collapsed') === 'expanded') {
        summaryEl?.classList.add('d-none');
        window.bootstrap?.Collapse.getOrCreateInstance(moversEl, { toggle: false }).show();
    }

    moversEl?.addEventListener('show.bs.collapse', () => {
        localStorage.setItem('movers-collapsed', 'expanded');
        iconEl?.classList.replace('fa-chevron-right', 'fa-chevron-down');
        moversTitleEl?.setAttribute('title', 'Collapse');
        moversTitleEl?.setAttribute('aria-expanded', 'true');
        summaryEl?.classList.add('d-none');
    });

    moversEl?.addEventListener('hide.bs.collapse', () => {
        localStorage.setItem('movers-collapsed', 'collapsed');
        iconEl?.classList.replace('fa-chevron-down', 'fa-chevron-right');
        moversTitleEl?.setAttribute('title', 'Expand');
        moversTitleEl?.setAttribute('aria-expanded', 'false');
        summaryEl?.classList.remove('d-none');
    });
</script>

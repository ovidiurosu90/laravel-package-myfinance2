<script type="module">
    $(document).ready(function () {
        const $movers = $('#biggest-movers');
        const $moversTitleEl = $('#biggest-movers-title');
        const $iconEl = $moversTitleEl.find('i');
        const $summary = $('#movers-summary');

        // Register listeners first so show.bs.collapse can drive summary visibility
        $movers
            .on('show.bs.collapse', function () {
                localStorage.setItem('movers-collapsed', 'expanded');
                $iconEl.removeClass('fa-chevron-right').addClass('fa-chevron-down');
                $moversTitleEl.attr('title', 'Collapse').attr('aria-expanded', 'true');
                $summary.addClass('d-none');
            })
            .on('hide.bs.collapse', function () {
                localStorage.setItem('movers-collapsed', 'collapsed');
                $iconEl.removeClass('fa-chevron-down').addClass('fa-chevron-right');
                $moversTitleEl.attr('title', 'Expand').attr('aria-expanded', 'false');
                $summary.removeClass('d-none');
            });

        // Restore saved state — listeners already registered so show.bs.collapse is caught
        if (localStorage.getItem('movers-collapsed') === 'expanded') {
            window.bootstrap?.Collapse.getOrCreateInstance($movers[0], { toggle: false }).show();
        }
    });
</script>

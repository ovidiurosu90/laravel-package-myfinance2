<script type="module">
    $(document).ready(function () {
        // Summary visibility only; collapse state is persisted centrally in scripts/card-collapse.
        const $panel   = $('#dip-buying-panel');
        const $summary = $('#dip-buying-summary');

        $panel
            .on('show.bs.collapse', function () {
                $summary.addClass('d-none');
            })
            .on('hide.bs.collapse', function () {
                $summary.removeClass('d-none');
            });

        // Toggle the collapsed deeper ladder bands; the row stays so it can be re-collapsed.
        document.querySelectorAll('.dip-ladder-more-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const extras   = btn.closest('table').querySelectorAll('.dip-ladder-extra');
                const expanded = btn.classList.toggle('dip-ladder-expanded');
                extras.forEach(function (r) {
                    r.classList.toggle('d-none', !expanded);
                });
                btn.innerHTML = expanded ? '&hellip; fewer bands' : '&hellip; deeper bands';
            });
        });
    });
</script>

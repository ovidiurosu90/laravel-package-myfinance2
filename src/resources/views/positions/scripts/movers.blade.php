<script type="module">
    $(document).ready(function () {
        // Summary visibility only; collapse state is persisted centrally in scripts/card-collapse.
        const $movers  = $('#biggest-movers');
        const $summary = $('#movers-summary');

        $movers
            .on('show.bs.collapse', function () {
                $summary.addClass('d-none');
            })
            .on('hide.bs.collapse', function () {
                $summary.removeClass('d-none');
            });
    });
</script>

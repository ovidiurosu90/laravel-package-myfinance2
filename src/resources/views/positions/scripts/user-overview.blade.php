<script type="module">
    $(document).ready(function () {
        const $overview = $('#user-overview');
        const $summary = $('#user-overview-summary');

        $overview
            .on('hide.bs.collapse', function () {
                $summary.removeClass('d-none');
            })
            .on('show.bs.collapse', function () {
                $summary.addClass('d-none');
            });
    });
</script>

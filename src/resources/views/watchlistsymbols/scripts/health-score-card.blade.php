@if(!empty($health_score))
<script type="module">
    $(document).ready(function () {
        const LS_KEY = 'watchlist_portfolio_health_expanded';
        const $body = $('#portfolio-health-body');
        const $summary = $('#portfolio-health-summary');
        const $toggle = $('#portfolio-health-toggle');

        $body
            .on('hide.bs.collapse', function () {
                localStorage.setItem(LS_KEY, 'false');
                $summary.removeClass('d-none');
            })
            .on('show.bs.collapse', function () {
                localStorage.setItem(LS_KEY, 'true');
                $summary.addClass('d-none');
            })
            .on('shown.bs.collapse', function () {
                _initTierTables();
            });

        if (localStorage.getItem(LS_KEY) === 'true') {
            $body.addClass('show');
            $toggle.removeClass('collapsed').attr('aria-expanded', 'true');
            $summary.addClass('d-none');
            _initTierTables();
        }

        function _initTierTables() {
            ['health-pgs-table', 'health-br-table'].forEach(function (id) {
                const $t = $('#' + id);
                if (!$t.length || $.fn.DataTable.isDataTable($t)) {
                    return;
                }
                $t.DataTable({
                    paging:    false,
                    searching: false,
                    info:      false,
                    autoWidth: false,
                    order:     [[2, 'desc']],
                    columnDefs: [
                        { targets: [1, 2, 3, 4], className: 'text-end' },
                        { targets: [1, 2, 3, 4], type: 'num' },
                    ],
                });
            });
        }
    });
</script>
@endif

@if(!empty($health_score))
<style>
    /* Section links inside the card: the chevron points down while the section is open. */
    .health-collapse-link .fa-chevron-right { transition: .2s transform ease-in-out; }
    .health-collapse-link[aria-expanded="true"] .fa-chevron-right { transform: rotate(90deg); }
</style>
<script type="module">
    $(document).ready(function () {
        const LS_KEY = 'watchlist_portfolio_health_expanded';
        const $body = $('#portfolio-health-body');
        const $summary = $('#portfolio-health-summary');
        const $toggle = $('#portfolio-health-toggle');
        // The two tier tables: open by default, collapsed only when the user collapsed them last time.
        const TABLES_LS_KEY = 'watchlist_portfolio_health_tables_expanded';
        const $tables = $('#health-symbol-tables');

        // Collapse events bubble: ignore the ones from sections nested in the card (the approach comparison).
        $body
            .on('hide.bs.collapse', function (e) {
                if (e.target !== this) {
                    return;
                }
                localStorage.setItem(LS_KEY, 'false');
                $summary.removeClass('d-none');
            })
            .on('show.bs.collapse', function (e) {
                if (e.target !== this) {
                    return;
                }
                localStorage.setItem(LS_KEY, 'true');
                $summary.addClass('d-none');
            })
            .on('shown.bs.collapse', function (e) {
                if (e.target !== this) {
                    return;
                }
                _initTierTables();
            });

        $tables
            .on('hide.bs.collapse', function (e) {
                if (e.target === this) {
                    _store(TABLES_LS_KEY, 'false');
                }
            })
            .on('show.bs.collapse', function (e) {
                if (e.target === this) {
                    _store(TABLES_LS_KEY, 'true');
                }
            })
            .on('shown.bs.collapse', function (e) {
                if (e.target === this) {
                    // Columns are measured while visible, so re-measure after opening.
                    $(this).find('table.dataTable').DataTable().columns.adjust();
                }
            });
        if (_read(TABLES_LS_KEY) === 'false') {
            $tables.removeClass('show');
            $('[aria-controls="health-symbol-tables"]').addClass('collapsed').attr('aria-expanded', 'false');
        }

        if (localStorage.getItem(LS_KEY) === 'true') {
            $body.addClass('show');
            $toggle.removeClass('collapsed').attr('aria-expanded', 'true');
            $summary.addClass('d-none');
            _initTierTables();
        }

        // Browser storage can be unavailable (private window, blocked site data); the default then applies.
        function _read(key) {
            try {
                return localStorage.getItem(key);
            } catch (e) {
                return null;
            }
        }

        function _store(key, value) {
            try {
                localStorage.setItem(key, value);
            } catch (e) {
                // Not remembered; nothing else to do.
            }
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

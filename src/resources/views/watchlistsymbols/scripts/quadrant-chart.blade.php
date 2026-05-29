@php $symbolsData = $quadrant['symbols'] ?? []; @endphp
<script type="module">
$(document).ready(function ()
{
    const symbolsData = @json($symbolsData);
    const dtInstances = {};
    const quadrantIds = {
        'STEADY_GROWERS':   'qdt-steady',
        'VOLATILE_WINNERS': 'qdt-volatile',
        'DEAD_WEIGHT':      'qdt-dead',
        'DANGER_ZONE':      'qdt-danger',
    };

    const quadrantSummaryConfig = {
        'STEADY_GROWERS':   { label: 'Accumulate', color: '#1a7a3c', id: 'qcs-accumulate' },
        'VOLATILE_WINNERS': { label: 'Hold',       color: '#856404', id: 'qcs-hold' },
        'DEAD_WEIGHT':      { label: 'Reduce',     color: '#495057', id: 'qcs-reduce' },
        'DANGER_ZONE':      { label: 'Exit',       color: '#842029', id: 'qcs-exit' },
    };

    function fmtAnn(val)
    {
        if (val === null || val === undefined) return '—';
        const sign     = val >= 0 ? '+' : '';
        const cls      = val >= 0 ? 'text-success' : 'text-danger';
        const decimals = Math.abs(val) >= 100 ? 0 : 2;
        return `<span class="${cls}">${sign}${parseFloat(val).toFixed(decimals)}%</span>`;
    }

    function fmtRisk(val)
    {
        if (val === null || val === undefined) return '—';
        const cls = val > 3.0 ? 'text-danger' : val > 2.0 ? 'text-warning' : 'text-success';
        return `<span class="${cls}">${parseFloat(val).toFixed(2)}x</span>`;
    }

    function formatPeriod(days)
    {
        if (!days || days < 1) return '';
        if (days < 30) return `${days}d`;
        const months = Math.round(days / 30.44);
        if (months < 12) return `${months}m`;
        const years = Math.floor(months / 12);
        const rem   = months % 12;
        return rem > 0 ? `${years}y ${rem}m` : `${years}y`;
    }

    function fmtOwnedNow(held, gainPct, isAnn, days)
    {
        if (!held) return '<span class="text-muted">—</span>';
        if (gainPct === null || gainPct === undefined) return '<span class="opacity-50">✓</span>';
        const sign   = gainPct >= 0 ? '+' : '';
        const cls    = gainPct >= 0 ? 'text-success' : 'text-danger';
        const suffix = isAnn ? '/y' : '';
        const period = days ? ` <span class="text-muted">· ${formatPeriod(days)}</span>` : '';
        return `<span class="opacity-50"><span class="${cls}">${sign}${parseFloat(gainPct).toFixed(2)}%${suffix}</span>${period}</span>`;
    }

    function fmtOwnedEver(heldEver, gainPct, isAnn, periodDisplay)
    {
        if (!heldEver) return '<span class="text-muted">—</span>';
        if (gainPct === null || gainPct === undefined) return '<span class="opacity-50">✓</span>';
        const sign   = gainPct >= 0 ? '+' : '';
        const cls    = gainPct >= 0 ? 'text-success' : 'text-danger';
        const suffix = isAnn ? '/y' : '';
        let period = '';
        if (periodDisplay) {
            const m = periodDisplay.match(/^(\d+) windows? (.+)$/);
            if (m) {
                const n    = parseInt(m[1], 10);
                const dur  = m[2].replace(/^\(|\)$/g, '');
                const tip  = `${n} holding window${n !== 1 ? 's' : ''}`;
                period = ` <span class="text-muted">· ${dur}<span data-bs-toggle="tooltip" title="${tip}">*</span></span>`;
            } else {
                period = ` <span class="text-muted">· ${periodDisplay}</span>`;
            }
        }
        return `<span class="opacity-50"><span class="${cls}">${sign}${parseFloat(gainPct).toFixed(2)}%${suffix}</span>${period}</span>`;
    }

    Object.entries(quadrantIds).forEach(([q, id]) =>
    {
        dtInstances[q] = $(`#${id}`).DataTable({
            paging:    false,
            searching: false,
            info:      false,
            order:     [[1, 'desc']],
            layout:    { topStart: null, topEnd: null, bottomStart: null, bottomEnd: null },
            initComplete: function()
            {
                $('<tr>'
                    + '<th class="border-0 p-0"></th>'
                    + '<th colspan="2" class="text-center p-0 border-top-0"'
                    +   ' style="border: 1px solid #dee2e6; border-top: none; line-height: 1">'
                    +   '<span class="text-muted fw-normal" style="font-size:0.65rem">↕ watchlist period</span>'
                    + '</th>'
                    + '<th colspan="2" class="text-center p-0"'
                    +   ' style="border: 1px solid #dee2e6; border-top: none; line-height: 1">'
                    +   '<span class="text-muted fw-normal" style="font-size:0.65rem">your position</span>'
                    + '</th>'
                    + '</tr>').prependTo($(this.api().table().header()));
            },
            columns: [
                {
                    data:   'symbol',
                    title:  'Symbol',
                    render: (d, t, row) =>
                    {
                        if (t !== 'display') return d;
                        if (row.isExited) {
                            return `<span class="opacity-50">${d} `
                                + `<i class="fa fa-history fa-xs" aria-hidden="true"`
                                + ` data-bs-toggle="tooltip"`
                                + ` title="Previously held; no longer in portfolio or watchlist"`
                                + `></i></span>`;
                        }
                        return row.isOwned ? `<strong>${d}</strong>` : d;
                    },
                },
                {
                    data:      'ann',
                    title:     '<span data-bs-toggle="tooltip" title="Raw return for 3M/6M; CAGR for 1Y/2Y. Switches with the period selector.">Gain</span>',
                    className: 'text-end',
                    render:    (d, t) => t !== 'display' ? (d ?? -9999) : fmtAnn(d),
                },
                {
                    data:      'relDd',
                    title:     '<span data-bs-toggle="tooltip" title="Max drawdown relative to VUSA.AS over the same period. 1.0x = same risk as benchmark; higher = more volatile.">Risk</span>',
                    className: 'text-end',
                    render:    (d, t) => t !== 'display' ? d : fmtRisk(d),
                },
                {
                    data:      'isOwned',
                    title:     '<span data-bs-toggle="tooltip" title="Currently holding. Shows the annualized return (CAGR) on YOUR open position if held ≥ 1 year, otherwise raw gain. This is your money on this position and can differ from the quadrant placement, which uses the symbol\'s MARKET return over the period. Holding period shown alongside.">Owned Now</span>',
                    className: 'text-center',
                    render:    (d, t, row) =>
                    {
                        if (t !== 'display') return d ? 1 : 0;
                        return fmtOwnedNow(d, row.openGainPct, row.openIsAnn, row.openDays);
                    },
                },
                {
                    data:      'ownedEver',
                    title:     '<span data-bs-toggle="tooltip" title="Ever held (including exited positions). Shows YOUR annualized return (CAGR) across all holding windows if total time held ≥ 1 year, otherwise raw gain. This is your money across every window and can differ from the quadrant placement, which uses the symbol\'s MARKET return.">Owned Ever</span>',
                    className: 'text-center',
                    render:    (d, t, row) =>
                    {
                        if (t !== 'display') return d ? 1 : 0;
                        return fmtOwnedEver(d, row.overallGainPct, row.overallIsAnn, row.overallPeriodDisplay);
                    },
                },
            ],
            language:      { emptyTable: '<span class="text-muted small">None</span>' },
            drawCallback:  function()
            {
                $('[data-bs-toggle="tooltip"]', this.api().table().node()).tooltip();
            },
        });
    });

    function updateSummary(period)
    {
        const counts = {
            'STEADY_GROWERS':   { total: 0, owned: 0 },
            'VOLATILE_WINNERS': { total: 0, owned: 0 },
            'DEAD_WEIGHT':      { total: 0, owned: 0 },
            'DANGER_ZONE':      { total: 0, owned: 0 },
        };
        symbolsData.forEach(sym =>
        {
            const pd = sym.periods[period];
            if (!pd || !pd.quadrant) return;
            counts[pd.quadrant].total++;
            if (sym.isOwned) counts[pd.quadrant].owned++;
        });
        Object.entries(quadrantSummaryConfig).forEach(([q, cfg]) =>
        {
            const { total, owned } = counts[q];
            const watchlist = total - owned;
            const $span = $(`#${cfg.id}`);
            $span
                .tooltip('dispose')
                .attr('title', `${owned} owned · ${watchlist} watchlist only`)
                .html(`<span style="color:${cfg.color}">${cfg.label} ${total}</span>`)
                .tooltip();
        });
    }

    function populateTables(period)
    {
        Object.values(dtInstances).forEach(t => t.clear());

        symbolsData.forEach(sym =>
        {
            const pd = sym.periods[period];
            if (!pd || pd.quadrant === null) return;
            const dt = dtInstances[pd.quadrant];
            if (!dt) return;

            dt.row.add({
                symbol:               sym.symbol,
                isOwned:              sym.isOwned,
                ownedEver:            sym.ownedEver,
                isExited:             sym.isExited,
                ann:                  pd.ann,
                relDd:                sym.relDd,
                openGainPct:          sym.openGainPct,
                openIsAnn:            sym.openIsAnn,
                openDays:             sym.openDays,
                overallGainPct:       sym.overallGainPct,
                overallIsAnn:         sym.overallIsAnn,
                overallPeriodDisplay: sym.overallPeriodDisplay,
            });
        });

        Object.values(dtInstances).forEach(t => t.draw());
        updateSummary(period);
    }

    populateTables('1y');


    $('#quadrant-period-btns button').on('click', function ()
    {
        const period = $(this).data('period');
        $('#quadrant-period-btns button').removeClass('active');
        $(this).addClass('active');
        populateTables(period);
    });

    const LS_KEY_QUADRANT = 'watchlist_quadrant_chart_expanded';
    const $summary = $('#quadrant-chart-summary');
    const $quadrantToggle = $('#quadrant-chart-toggle');

    $('#quadrant-chart-collapse')
        .on('hide.bs.collapse', function () {
            localStorage.setItem(LS_KEY_QUADRANT, 'false');
            $summary.removeClass('d-none');
        })
        .on('show.bs.collapse', function () {
            localStorage.setItem(LS_KEY_QUADRANT, 'true');
            $summary.addClass('d-none');
        })
        .on('shown.bs.collapse', function ()
        {
            $('[data-bs-toggle="tooltip"]', this).tooltip();
            Object.values(dtInstances).forEach(t => t.columns.adjust());
        });

    if (localStorage.getItem(LS_KEY_QUADRANT) === 'true') {
        $('#quadrant-chart-collapse').addClass('show');
        $quadrantToggle.removeClass('collapsed').attr('aria-expanded', 'true');
        $summary.addClass('d-none');
        Object.values(dtInstances).forEach(t => t.columns.adjust());
    }
});
</script>

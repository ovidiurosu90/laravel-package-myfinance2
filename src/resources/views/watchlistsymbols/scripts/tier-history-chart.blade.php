<script type="module">
$(document).ready(function()
{
    const modalEl = document.getElementById('tier-history-modal');
    if (!modalEl) {
        return;
    }
    const container = document.getElementById('thm-chart');

    @include('myfinance2::watchlistsymbols.scripts.partials.tier-chart-helpers')

    const posContainer = document.getElementById('thm-pos-chart');
    let posChart      = null;
    let chart         = null;
    let currentSymbol = null;
    let requestId     = 0;

    function disposeChart()
    {
        if (chart) {
            chart.remove();
            chart = null;
        }
        if (posChart) {
            posChart.remove();
            posChart = null;
        }
        container.innerHTML    = '';
        posContainer.innerHTML = '';
    }

    function showState(state)
    {
        $('#thm-loading').toggle(state === 'loading');
        $('#thm-empty').toggle(state === 'empty');
        $(container).toggle(state === 'chart');
        $('#thm-note').toggle(state === 'chart');
        if (state !== 'chart') {
            $('#thm-position').hide();
        }
    }

    function swatch(color)
    {
        return $('<span class="thm-swatch me-1"></span>').css('background-color', color);
    }

    // Days the measure deciding the tier switches. It is the 1Y market return every day; your own return
    // stands in only on days without a usable market figure (rare), and the tier can jump there.
    function basisMarkers(data)
    {
        const markers = [];
        data.points.forEach((p, i) =>
        {
            const prev = data.points[i - 1];
            if (prev && prev.basis !== p.basis) {
                markers.push({
                    time: p.time, position: 'belowBar', color: '#6c757d', shape: 'circle', text: p.basis,
                });
            }
        });
        return markers;
    }

    @include('myfinance2::watchlistsymbols.scripts.partials.tier-position-chart')

    // A. The tier chart: the 1Y market return per day, coloured by tier, the tier strip and your trades.
    function buildChart(data)
    {
        chart = createTierChart(container);
        const basis = addPctLine(chart, data.points.map((p) => ({ time: p.time, value: p.value, color: p.color })));
        addTierLines(basis, data.lines, data.tiers);
        setTradeMarkers(basis, data.points, basisMarkers(data));
        addTierStrip(chart, data.points);
        finishTierChart(chart);

        // The hover label: the two closes the 1Y return is measured between (the percentage itself is on
        // the right axis), then your buys and sells of that day.
        bindHoverLabel(chart, container, basis, data.points, (p) => p.value, (p) =>
        {
            const out = p.window
                ? [{ text: '1Y ago ' + p.window.ago }, { text: 'Now ' + p.window.now }]
                : [];
            return out.concat(tradeLines(p.trades));
        });
    }

    // The second chart, only for a symbol you have held (it needs your own return).
    function renderPosition(data)
    {
        $('#thm-position').toggle(!!data.position);
        if (!data.position) {
            return;
        }
        const tradesByTime = {};
        data.points.forEach((p) =>
        {
            tradesByTime[p.time] = p.trades;
        });
        posChart = buildPositionChart(
            posContainer, data.position, data.lines, data.tiers, tradesByTime
        );
        renderPeriods(data.position);
        syncTimeScales(chart, posChart);
    }

    // The (i) next to section A's title: what the chart shows, then the days spent in each tier over the
    // last 12 months and over all stored days. Rebuilt for every symbol.
    function renderTierInfo(data)
    {
        const recent = {};
        data.totals_recent.forEach((t) =>
        {
            recent[t.tier] = t;
        });
        const $table = $('<table class="thm-info-table"></table>').append($('<tr></tr>').append(
            $('<th></th>').text('Tier'),
            $('<th></th>').text('Last 12 months'),
            $('<th></th>').text('All stored days')
        ));
        data.totals_all.forEach((t) =>
        {
            const r = recent[t.tier];
            $table.append($('<tr></tr>').append(
                $('<td></td>').append(swatch(t.color), document.createTextNode(t.label)),
                $('<td></td>').text(r ? r.days + ' d (' + r.pct + ')' : '-'),
                $('<td></td>').text(t.days + ' d (' + t.pct + ')')
            ));
        });
        const $html = $('<div></div>').append(
            $('<div class="thm-info-text"></div>').text('Each trading day: the stock\'s return over the previous'
                + ' 12 months, coloured by the tier it falls in (lines at 0 / 5 / 10 / 15 %); the strip under the chart'
                + ' repeats it. Your trades do not change it; B / S only mark when you bought and sold.'),
            $table
        );
        setInfoTooltip($('#thm-tier-info'), $html);
    }

    // Puts $html in the (i) icon's tooltip. sanitize: false keeps the table (Bootstrap's allow-list drops
    // it); every value in it went in through .text(), so it is escaped.
    function setInfoTooltip($icon, $html)
    {
        $icon.tooltip('dispose');
        $icon.attr('title', $html.html()).tooltip({
            html: true, sanitize: false, customClass: 'thm-tooltip', placement: 'bottom',
        });
    }

    function load(symbol)
    {
        const reqId = ++requestId;
        showState('loading');
        $.ajax({
            type: 'GET',
            url: "{{ route('myfinance2::watchlist-symbols.tier-history') }}",
            data: { symbol: symbol },
            success: function(data)
            {
                if (reqId !== requestId) {
                    return; // the modal closed or moved to another symbol meanwhile
                }
                if (!data.points.length) {
                    showState('empty');
                    return;
                }
                showState('chart');
                buildChart(data);
                renderPosition(data);
                renderTierInfo(data);
            },
            error: function()
            {
                if (reqId === requestId) {
                    showState('empty');
                }
            },
        });
    }

    modalEl.addEventListener('show.bs.modal', function(event)
    {
        currentSymbol = event.relatedTarget ? $(event.relatedTarget).data('symbol') : null;
        $('#thm-symbol').text(currentSymbol || '');
        disposeChart();
        showState('loading');
    });

    // Built once the dialog is visible, so the container has a measurable width.
    modalEl.addEventListener('shown.bs.modal', function()
    {
        if (currentSymbol) {
            load(currentSymbol);
        }
    });

    modalEl.addEventListener('hidden.bs.modal', function()
    {
        requestId++;
        disposeChart();
    });

    window.addEventListener('resize', function()
    {
        if (chart) {
            chart.applyOptions({ width: container.clientWidth });
        }
        if (posChart) {
            posChart.applyOptions({ width: posContainer.clientWidth });
        }
    });
});
</script>

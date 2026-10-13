{{--
    B. "Your holdings" chart of the tier history modal, built with the same helpers, size, scale, tier lines,
    tier colours, tier strip, B / S markers and hover label as the tier chart above it. Only days you held
    shares, one holding period at a time: solid, your return on the holding; dashed, the same trades made
    in VUSA (S&P 500). Both are raw under a year and yearly from a year, like the tier, and coloured by the
    tier they fall in. Included inside the tier-history-chart script scope (uses tier-chart-helpers).
--}}
    // tradesByTime: date => that day's trade lines from the tier chart's points.
    function buildPositionChart(el, position, lines, tiers, tradesByTime)
    {
        const c      = createTierChart(el);
        const points = position.points.map((p) => Object.assign({}, p, { trades: tradesByTime[p.time] || [] }));
        const held   = points.filter((p) => p.value !== null);
        // Whitespace on days without shares, so the lines break between holding periods.
        const line   = (key, colorKey) => points.map((p) => (p[key] !== null && p[key] !== undefined
            ? { time: p.time, value: p[key], color: p[colorKey] } : { time: p.time }));

        addPctLine(c, line('bench_value', 'bench_color'), { lineStyle: LightweightCharts.LineStyle.Dashed });
        const yours = addPctLine(c, line('value', 'color'));
        addTierLines(yours, lines, tiers);
        setTradeMarkers(yours, held);
        addTierStrip(c, points.map((p) => (p.value !== null ? { time: p.time, color: p.color } : { time: p.time })));
        finishTierChart(c);

        bindHoverLabel(c, el, yours, points, (p) => p.value, (p) =>
        {
            if (p.value === null) {
                return tradeLines(p.trades);
            }
            const out = [{
                sample: 'solid', color: p.color,
                text: 'Your return since ' + p.since_label + ': ' + pctFormatter(p.value) + ' (' + p.label + ')',
            }];
            if (p.bench_value !== null) {
                out.push({
                    sample: 'dashed', color: p.bench_color,
                    text: 'VUSA, same trades: ' + pctFormatter(p.bench_value) + ' (' + p.bench_label + ')',
                });
            }
            return out.concat(tradeLines(p.trades));
        });

        return c;
    }

    // The (i) next to section B's title: what the chart shows, then every holding period against the same
    // trades in VUSA, and all periods together as yearly rates.
    function renderPeriods(position)
    {
        const diffCell = (row) => $('<td></td>').addClass(row.diff_positive ? 'text-success' : 'text-danger')
            .text(row.diff);
        const $table   = $('<table class="thm-info-table"></table>').append($('<tr></tr>').append(
            $('<th></th>').text('Holding period'),
            $('<th></th>').text('Days'),
            $('<th></th>').text('Your return'),
            $('<th></th>').text('VUSA, same trades'),
            $('<th></th>').text('Difference')
        ));
        position.periods.slice().reverse().forEach((row) =>
        {
            $table.append($('<tr></tr>').append(
                $('<td></td>').text(row.from + ' → ' + row.to),
                $('<td></td>').text(row.days),
                $('<td></td>').text(row.yours),
                $('<td></td>').text(row.bench),
                diffCell(row)
            ));
        });
        if (position.total) {
            const t = position.total;
            $table.append($('<tr class="fw-semibold"></tr>').append(
                $('<td></td>').text('All periods, since ' + t.from),
                $('<td></td>'),
                $('<td></td>').text(t.yours),
                $('<td></td>').text(t.bench),
                diffCell(t)
            ));
        }
        setInfoTooltip($('#thm-pos-info'), $('<div></div>').append(
            $('<div class="thm-info-text"></div>').text('Only days you held shares, one holding period at a time.'
                + ' Solid: your return on the holding. Dashed: the same money put into VUSA (S&P 500) on the same'
                + ' days and taken out in the same proportions, in the stock\'s currency. Both are raw under a'
                + ' year and yearly from a year, like the tier, and coloured by the tier they fall in. The gap is'
                + ' what choosing this stock added or cost. VUSA counts price only (no dividends) and no fees.'),
            $table
        ));
    }

    // Keep both charts on the same date range when either is scrolled or zoomed.
    function syncTimeScales(a, b)
    {
        let syncing = false;
        const follow = (to) => (range) =>
        {
            if (syncing || !range) {
                return;
            }
            syncing = true;
            try {
                to.timeScale().setVisibleRange(range);
            } catch (e) {
                // The other chart may not cover the whole range yet; leave it as it is.
            }
            syncing = false;
        };
        a.timeScale().subscribeVisibleTimeRangeChange(follow(b));
        b.timeScale().subscribeVisibleTimeRangeChange(follow(a));
    }

{{--
    Shared building blocks of the two tier history charts (A. tier, B. your position), so both use the
    same options, scale, pane sizes, tier strip, B / S markers and hover label. Included inside the
    tier-history-chart script scope.
--}}
    // Main line pane and the tier strip under it, the same in both charts.
    const PANE_HEIGHTS = [230, 32];

    function pctFormatter(v)
    {
        return (v >= 0 ? '+' : '') + v.toFixed(1) + '%';
    }

    function createTierChart(el)
    {
        return LightweightCharts.createChart(el, {
            width: el.clientWidth,
            height: PANE_HEIGHTS.reduce((a, b) => a + b, 0),
            layout: { attributionLogo: false },
            grid: { vertLines: { visible: false }, horzLines: { color: 'rgba(42, 46, 57, 0.15)' } },
            // Small margins so the scale ends close to the highest and lowest points shown (the default
            // 20 % / 10 % padding left an empty band above the data).
            rightPriceScale: { borderVisible: false, scaleMargins: { top: 0.06, bottom: 0.04 } },
            timeScale: { borderVisible: false },
        });
    }

    // A percentage line in the main pane; points are { time } (whitespace) or { time, value[, color] }.
    function addPctLine(c, points, options)
    {
        const series = c.addSeries(LightweightCharts.LineSeries, Object.assign({
            lineWidth: 2,
            priceFormat: { type: 'custom', minMove: 0.01, formatter: pctFormatter },
            lastValueVisible: true,
            priceLineVisible: false,
        }, options || {}), 0);
        series.setData(points);
        return series;
    }

    function addTierLines(series, lines, tiers)
    {
        // Each line is the lower bound of the next tier up (0 = Bronze ... 15 = Platinum).
        const names = ['Bronze', 'Silver', 'Gold', 'Platinum'];
        lines.forEach((value, i) =>
        {
            const tier = tiers.find((t) => t.label === names[i]);
            series.createPriceLine({
                price: value,
                color: tier ? tier.color : '#999',
                lineWidth: 1,
                lineStyle: LightweightCharts.LineStyle.Dashed,
                axisLabelVisible: true,
                title: names[i],
            });
        });
    }

    // The tier of each day as a coloured strip in the second pane. An overlay scale draws no axis; hiding
    // the pane's own scale instead (priceScale().applyOptions({ visible: false })) throws on draw.
    function addTierStrip(c, points)
    {
        const strip = c.addSeries(LightweightCharts.HistogramSeries, {
            priceScaleId: 'tier-ribbon', lastValueVisible: false, priceLineVisible: false,
        }, 1);
        strip.setData(points.map((p) => (p.color ? { time: p.time, value: 1, color: p.color } : { time: p.time })));
    }

    // Proportional sizes (setHeight() before the first layout leaves the lower pane at 0 px), then fit.
    function finishTierChart(c)
    {
        c.panes().forEach((pane, i) => pane.setStretchFactor(PANE_HEIGHTS[i] || 32));
        c.timeScale().fitContent();
    }

    // B / S markers from the day's trades (already formatted in the BE), plus any extra markers.
    function setTradeMarkers(series, points, extra)
    {
        const markers = (extra || []).slice();
        points.forEach((p) =>
        {
            const trades = p.trades || [];
            if (trades.some((t) => t.buy)) {
                markers.push({ time: p.time, position: 'aboveBar', color: '#198754', shape: 'circle', text: 'B' });
            }
            if (trades.some((t) => !t.buy)) {
                markers.push({ time: p.time, position: 'aboveBar', color: '#dc3545', shape: 'circle', text: 'S' });
            }
        });
        markers.sort((a, b) => (a.time < b.time ? -1 : (a.time > b.time ? 1 : 0)));
        LightweightCharts.createSeriesMarkers(series, markers);
    }

    // Series times are 'YYYY-MM-DD' strings; normalise a business-day object just in case.
    function timeKey(t)
    {
        if (t && typeof t === 'object' && t.year) {
            return t.year + '-' + String(t.month).padStart(2, '0') + '-' + String(t.day).padStart(2, '0');
        }
        return t;
    }

    // A small label just above the hovered point. linesOf(point) returns [{ text, cls, sample, color }] (empty: no
    // label); valueOf(point) gives the y the label sits above. Trade lines are appended by the caller.
    function bindHoverLabel(c, el, series, points, valueOf, linesOf)
    {
        const byTime = {};
        points.forEach((p) =>
        {
            byTime[p.time] = p;
        });
        const $tip = $('<div class="thm-tip"></div>').hide().appendTo(el);
        c.subscribeCrosshairMove((param) =>
        {
            const p     = param.time && param.point ? byTime[timeKey(param.time)] : null;
            const lines = p ? linesOf(p) : [];
            if (!lines.length) {
                $tip.hide();
                return;
            }
            $tip.empty();
            lines.forEach((line) =>
            {
                const $line = $('<div></div>').addClass(line.cls || '');
                if (line.sample) {
                    // A short sample of the line's style (solid / dashed), so each value maps to its line.
                    $line.append($('<span class="thm-line-sample"></span>').addClass('thm-line-' + line.sample)
                        .css('border-top-color', line.color || ''));
                }
                $tip.append($line.append(document.createTextNode(line.text)));
            });
            $tip.show();
            const value = valueOf(p);
            const y     = value !== null ? series.priceToCoordinate(value) : null;
            const at    = y !== null ? y : param.point.y;
            const w     = $tip.outerWidth();
            const h     = $tip.outerHeight();
            const left  = Math.max(0, Math.min(param.point.x - w / 2, c.timeScale().width() - w));
            const top   = at - h - 12;
            $tip.css({ left: left + 'px', top: (top < 0 ? at + 12 : top) + 'px' });
        });
    }

    function tradeLines(trades)
    {
        return (trades || []).map((t) => ({ text: t.text, cls: t.buy ? 'text-success' : 'text-danger' }));
    }

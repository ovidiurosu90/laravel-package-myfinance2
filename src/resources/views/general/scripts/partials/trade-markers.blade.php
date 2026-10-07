{{--
    Shared buy/sell chart markers used by the symbol chart modal (/positions,
    /watchlist-symbols "expand chart") and the orders form chart panel, so every symbol
    chart marks the same trades the same way.

    Defines loadTradeMarkers(options) and resetTradeMarkers() (plus the helpers they use)
    in the including scope; the including scope must provide a chart built by
    general.scripts.partials.symbol-baseline-chart (or any series accepted by
    LightweightCharts.createSeriesMarkers).

    Usage:
        @include('myfinance2::general.scripts.partials.trade-markers')

        loadTradeMarkers({
            symbol: 'NVDA', series: built.series, seriesData: data.series,
            accountId: '', isCurrent: function() { return myReq === reqId; },
        });
--}}
    // The markers primitive is owned by the series it was created on, so it is tracked
    // together with that series: a rebuilt chart yields a new series object and
    // therefore a fresh primitive, while repeated loads on the same chart just reset
    // the marker list.
    let tradeMarkersPrimitive = null;
    let tradeMarkersSeries    = null;

    // Snap a trade date onto an existing series day so its marker always lands on a
    // plotted bar. Exact match when the date is a trading day in the series; otherwise
    // the nearest earlier bar (e.g. a weekend trade falls back to the prior session).
    // times is the series' day strings in ascending order.
    function snapToSeriesTime(date, times)
    {
        if (times.indexOf(date) !== -1) {
            return date;
        }
        let best = null;
        for (let i = 0; i < times.length; i += 1) {
            if (times[i] <= date) {
                best = times[i];
            } else {
                break;
            }
        }
        return best !== null ? best : times[0];
    }

    // Build buy/sell markers from the symbol's trades. Same-day, same-action trades are
    // summed into one marker (B for buys, S for sells, in the shared success/danger
    // colours). On /positions only the opened account's trades are kept; where
    // accountId is empty (/watchlist-symbols, the orders form), every account's trades
    // are plotted.
    function buildTradeMarkers(trades, accountId, seriesData)
    {
        if (!seriesData || !seriesData.length) {
            return [];
        }
        const times     = seriesData.map((p) => p.time);
        const firstTime = times[0];
        const lastTime  = times[times.length - 1];

        const qtyByDayAction = {};
        (trades || []).forEach((t) =>
        {
            if (accountId && String(t.account_id) !== String(accountId)) {
                return;
            }
            const action = (t.action || '').toUpperCase();
            if (action !== 'BUY' && action !== 'SELL') {
                return;
            }
            const date = t.date;
            if (!date || date < firstTime || date > lastTime) {
                return; // outside the plotted range
            }
            const key = date + '|' + action;
            qtyByDayAction[key] = (qtyByDayAction[key] || 0) + (parseFloat(t.quantity) || 0);
        });

        const markers = Object.keys(qtyByDayAction).map((key) =>
        {
            const parts = key.split('|');
            const isBuy = parts[1] === 'BUY';
            return {
                time:     snapToSeriesTime(parts[0], times),
                position: 'aboveBar',
                color:    isBuy ? '#198754' : '#dc3545',
                shape:    'circle',
                text:     isBuy ? 'B' : 'S',
            };
        });

        // Lightweight Charts requires markers in ascending time order.
        markers.sort((a, b) => (a.time < b.time ? -1 : (a.time > b.time ? 1 : 0)));
        return markers;
    }

    // Call when the chart is disposed, so the removed series is not kept alive and the
    // next chart starts with a fresh primitive.
    function resetTradeMarkers()
    {
        tradeMarkersPrimitive = null;
        tradeMarkersSeries    = null;
    }

    function applyTradeMarkers(series, markers)
    {
        if (!series) {
            return;
        }
        if (tradeMarkersSeries === series && tradeMarkersPrimitive) {
            tradeMarkersPrimitive.setMarkers(markers);
            return;
        }
        tradeMarkersPrimitive = LightweightCharts.createSeriesMarkers(series, markers);
        tradeMarkersSeries    = series;
    }

    // Fetch the symbol's trades and overlay them as buy/sell markers on the chart.
    // options:
    //   symbol     required symbol whose trades are plotted
    //   series     required chart series the markers attach to
    //   seriesData required plotted points, used to place each marker on a trading day
    //   accountId  optional account id; when set, only that account's trades are plotted
    //   isCurrent  optional predicate; the response is dropped when it returns false
    //              (the chart was disposed or moved on while the request was in flight)
    function loadTradeMarkers(options)
    {
        if (!options.symbol || !options.series) {
            return;
        }
        $.ajax({
            type: 'GET',
            url: "{{ url('/get-trades') }}",
            data: { symbol: options.symbol },
            success: function(data)
            {
                if (options.isCurrent && !options.isCurrent()) {
                    return;
                }
                applyTradeMarkers(options.series, buildTradeMarkers(
                    data.trades || [], options.accountId || '', options.seriesData
                ));
            },
        });
    }

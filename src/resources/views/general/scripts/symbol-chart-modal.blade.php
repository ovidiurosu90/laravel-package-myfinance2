<script type="module">
$(document).ready(function()
{
    const modalEl = document.getElementById('symbol-chart-modal');
    if (!modalEl) {
        return;
    }
    const chartContainer = document.getElementById('symbol-chart-modal-chart');
    const emptyEl        = document.getElementById('symbol-chart-modal-empty');

    // Hide the expand icon for symbols that have no stored chart series
    // (e.g. BTC-USD), since there is nothing to enlarge.
    $('.symbol-chart-expand').each(function()
    {
        const series = (window.__symbolChartSeries || {})[$(this).data('symbol')];
        if (!series || !series.length) {
            $(this).hide();
        }
    });

    let symbolChart  = null;
    let symbolSeries = null; // the price series, kept so trade markers can attach to it
    let tradeMarkers = null; // createSeriesMarkers primitive for the buy/sell markers

    @include('myfinance2::general.scripts.partials.fmt-price')

    // Captured on show; the chart is built on shown.bs.modal so the container
    // has a measurable width.
    let currentSymbol    = null;
    let currentBaseValue = null;
    let currentAccountId = '';
    let currentCurrency  = '';

    function disposeChart()
    {
        if (symbolChart) {
            symbolChart.remove();
            symbolChart = null;
        }
        // The markers primitive is owned by the (now removed) series, so just drop
        // the references; a fresh set is created on the next open.
        symbolSeries = null;
        tradeMarkers = null;
        chartContainer.innerHTML = '';
    }

    function buildChart(symbol, baseValue)
    {
        disposeChart();

        const series = (window.__symbolChartSeries || {})[symbol];
        if (!series || !series.length) {
            chartContainer.style.display = 'none';
            emptyEl.style.display = 'block';
            return;
        }
        chartContainer.style.display = 'block';
        emptyEl.style.display = 'none';

        symbolChart = LightweightCharts.createChart(chartContainer, {
            width: chartContainer.clientWidth,
            height: 360,
            layout: { attributionLogo: false },
            localization: { priceFormatter: fmtPrice },
            grid: {
                vertLines: { visible: false },
                horzLines: { color: 'rgba(42, 46, 57, 0.2)' },
            },
            rightPriceScale: { borderVisible: false },
            timeScale: { borderVisible: false, timeVisible: false },
        });

        const seriesProperties = {
            lastValueVisible: true,
            priceFormat: { type: 'custom', minMove: 0.01, formatter: fmtPrice },
            topLineColor: 'rgba( 38, 166, 154, 1)',
            topFillColor1: 'rgba( 38, 166, 154, 0.28)',
            topFillColor2: 'rgba( 38, 166, 154, 0.05)',
            bottomLineColor: 'rgba( 239, 83, 80, 1)',
            bottomFillColor1: 'rgba( 239, 83, 80, 0.05)',
            bottomFillColor2: 'rgba( 239, 83, 80, 0.28)',
        };
        if (baseValue) {
            seriesProperties.baseValue = { type: 'price', price: baseValue };
        }

        const s = symbolChart.addSeries(LightweightCharts.BaselineSeries, seriesProperties);
        s.setData(series);
        symbolSeries = s;
        symbolChart.timeScale().fitContent();
    }

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
    // colours). On /positions only the opened account's trades are kept; on
    // /watchlist-symbols accountId is empty, so every account's trades are plotted.
    function buildTradeMarkers(trades, accountId, seriesData)
    {
        if (!seriesData || !seriesData.length) {
            return [];
        }
        const times     = seriesData.map((p) => p.time);
        const firstTime  = times[0];
        const lastTime   = times[times.length - 1];

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
            const parts  = key.split('|');
            const isBuy  = parts[1] === 'BUY';
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

    function applyTradeMarkers(markers)
    {
        if (!symbolSeries) {
            return;
        }
        if (tradeMarkers) {
            tradeMarkers.setMarkers(markers);
        } else {
            tradeMarkers = LightweightCharts.createSeriesMarkers(symbolSeries, markers);
        }
    }

    // Fetch the symbol's trades and overlay them as buy/sell markers on the chart.
    function loadTrades(symbol, accountId)
    {
        if (!symbolSeries) {
            return;
        }
        $.ajax({
            type: 'GET',
            url: "{{ url('/get-trades') }}",
            data: { symbol: symbol },
            success: function(data)
            {
                // Ignore a late response if the modal moved to another symbol or closed.
                if (!symbolSeries || currentSymbol !== symbol) {
                    return;
                }
                const seriesData = (window.__symbolChartSeries || {})[symbol];
                applyTradeMarkers(buildTradeMarkers(data.trades || [], accountId, seriesData));
            },
        });
    }

    function resizeChart()
    {
        if (symbolChart) {
            symbolChart.applyOptions({ width: chartContainer.clientWidth });
        }
    }

    @include('myfinance2::general.scripts.partials.range-bar', ['rangeBarId' => 'scm-range-bar'])

    @include('myfinance2::general.scripts.partials.populate-historical')

    // Compact, two-column position metrics from the trigger's formatted
    // data-attrs: market figures on the left, gains (value + %) on the right.
    function buildPositionOverview($icon)
    {
        const mkRow = function(label, value)
        {
            if (value === undefined || value === null || value === '') {
                return '';
            }
            return '<tr><th class="fw-normal text-muted text-nowrap">' + label
                 + '</th><td class="text-end">' + value + '</td></tr>';
        };
        const mkGain = function(label, value, pct)
        {
            return mkRow(label, value
                ? value + (pct ? ' (' + pct + ')' : '') : '');
        };
        const mkTable = function(rows)
        {
            return rows
                ? '<table class="table table-sm mb-0"><tbody>' + rows + '</tbody></table>'
                : '';
        };

        const left = mkRow('MValue', $icon.data('mvalue'))
            + mkRow('Cost Basis', $icon.data('cost-basis'))
            + mkRow('Avg Cost', $icon.data('avg-cost'));
        const right = mkGain('Day Gain',
                $icon.data('day-gain'), $icon.data('day-gain-pct'))
            + mkGain('Overall Gain',
                $icon.data('overall-gain'), $icon.data('overall-gain-pct'));

        if (!left && !right) {
            $('#scm-overall').html('').hide();
            return;
        }

        $('#scm-overall').html(
            '<div class="row g-3">'
          +   '<div class="col-md-6">' + mkTable(left) + '</div>'
          +   '<div class="col-md-6">' + mkTable(right) + '</div>'
          + '</div>'
        ).show();
    }

    function loadFinanceData(symbol, accountId, currency)
    {
        $('#scm-range-bar').html('');
        $.ajax({
            type: 'GET',
            url: "{{ url('/get-finance-data') }}",
            data: { symbol: symbol, account_id: accountId },
            success: function(data)
            {
                buildRangeBar(data, currency || data.currency || '');
            },
        });
    }

    // Populate when the modal opens (Bootstrap data-API passes the trigger as
    // relatedTarget).
    modalEl.addEventListener('show.bs.modal', function(event)
    {
        const icon = event.relatedTarget;
        if (!icon) {
            return;
        }
        const $icon = $(icon);

        // Drop any backfill feedback left from the previously opened symbol.
        setPopulateBusy(false);
        setPopulateStatus('');

        currentSymbol    = $icon.data('symbol');
        currentBaseValue = parseFloat($icon.data('base-value')) || null;
        currentAccountId = $icon.data('account-id') || '';
        currentCurrency  = $icon.data('currency') || '';

        const symbolName = $icon.data('symbol-name') || currentSymbol;

        const $link = $('#symbol-chart-modal-symbol-link');
        $link.text(currentSymbol)
             .attr('href', 'https://finance.yahoo.com/quote/' + currentSymbol);
        $('#symbol-chart-modal-name').html(symbolName);

        // Pre/post/at-close header (server-rendered, formatted with green/red)
        $('#scm-quote-details').html($icon.data('quote-header') || '');

        // Position overview: on the watchlist, reuse the position cards already
        // rendered in the row; on positions, build a compact metrics table from
        // the row's (already-formatted) values. Hidden when neither applies.
        const $opCards = $icon.closest('tr').find('.open-positions-cards').first();
        const $dialog  = $(modalEl).find('.modal-dialog');
        if ($opCards.length) {
            // Widen the dialog so the account cards sit side by side, wrapping
            // as space allows.
            $dialog.addClass('scm-wide');
            $('#scm-overall').html(
                '<div class="d-flex gap-2 flex-wrap align-items-start">'
                + $opCards.html() + '</div>'
            ).show();
        } else {
            $dialog.removeClass('scm-wide');
            buildPositionOverview($icon);
        }
    });

    modalEl.addEventListener('shown.bs.modal', function()
    {
        buildChart(currentSymbol, currentBaseValue);
        loadFinanceData(currentSymbol, currentAccountId, currentCurrency);
        loadTrades(currentSymbol, currentAccountId);
    });

    modalEl.addEventListener('hidden.bs.modal', function()
    {
        disposeChart();
    });

    window.addEventListener('resize', resizeChart);
});
</script>

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

    @include('myfinance2::general.scripts.partials.fmt-price')

    @include('myfinance2::general.scripts.partials.symbol-baseline-chart')

    @include('myfinance2::general.scripts.partials.trade-markers')

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
        resetTradeMarkers();
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

        const built = createSymbolBaselineChart(chartContainer, {
            series:    series,
            height:    360,
            baseValue: baseValue,
        });
        symbolChart  = built.chart;
        symbolSeries = built.series;
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
                ? '<table class="table table-sm mb-0 scm-metrics"><tbody>'
                  + rows + '</tbody></table>'
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
        // rendered in the row (side by side, wrapping as space allows); on positions,
        // build a compact metrics table from the row's (already-formatted) values.
        // Hidden when neither applies. The dialog size does not change with either,
        // so the chart is the same on both pages.
        const $opCards = $icon.closest('tr').find('.open-positions-cards').first();
        if ($opCards.length) {
            $('#scm-overall').html(
                '<div class="d-flex gap-2 flex-wrap align-items-start">'
                + $opCards.html() + '</div>'
            ).show();
        } else {
            buildPositionOverview($icon);
        }
    });

    modalEl.addEventListener('shown.bs.modal', function()
    {
        buildChart(currentSymbol, currentBaseValue);
        loadFinanceData(currentSymbol, currentAccountId, currentCurrency);

        const symbol = currentSymbol;
        const series = symbolSeries;
        loadTradeMarkers({
            symbol:     symbol,
            accountId:  currentAccountId,
            series:     series,
            seriesData: (window.__symbolChartSeries || {})[symbol],
            // Drop a late response if the modal closed or moved to another symbol.
            isCurrent:  function()
            {
                return symbolSeries === series && currentSymbol === symbol;
            },
        });
    });

    modalEl.addEventListener('hidden.bs.modal', function()
    {
        disposeChart();
    });

    window.addEventListener('resize', resizeChart);
});
</script>

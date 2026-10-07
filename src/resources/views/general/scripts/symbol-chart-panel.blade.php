{{--
    Fills general.partials.symbol-chart-panel for the symbol in the form's #symbol-input,
    on every form that takes one (orders, trades and price alerts, create and edit).
    Reloads on symbol blur, on the Get Finance Data button and on the Listed/Unlisted
    toggle.

    Usage (in footer_scripts, after the page's own finance script):
        @include('myfinance2::general.scripts.symbol-chart-panel')
--}}
<script type="module">
$(document).ready(function()
{
    const panel = document.getElementById('scp-panel-row');
    if (!panel) {
        return;
    }
    const chartContainer = document.getElementById('scp-chart');
    const emptyEl        = document.getElementById('scp-chart-empty');

    let chart       = null;
    let chartSeries = null; // the price series, kept so trade markers can attach to it
    let reqId       = 0;    // guards against out-of-order responses

    @include('myfinance2::general.scripts.partials.fmt-price')

    @include('myfinance2::general.scripts.partials.symbol-baseline-chart')

    @include('myfinance2::general.scripts.partials.trade-markers')

    function disposeChart()
    {
        if (chart) {
            chart.remove();
            chart = null;
        }
        // The markers primitive is owned by the (now removed) series, so just drop
        // the references; a fresh set is created on the next load.
        chartSeries = null;
        resetTradeMarkers();
        chartContainer.innerHTML = '';
    }

    function buildChart(series, baseValue)
    {
        disposeChart();

        if (!series || !series.length) {
            chartContainer.style.display = 'none';
            emptyEl.style.display = 'block';
            return;
        }
        chartContainer.style.display = 'block';
        emptyEl.style.display = 'none';

        const built = createSymbolBaselineChart(chartContainer, {
            series:    series,
            height:    300,
            baseValue: baseValue,
        });
        chart       = built.chart;
        chartSeries = built.series;
    }

    @include('myfinance2::general.scripts.partials.range-bar', ['rangeBarId' => 'scp-range-bar'])

    function clearPanel()
    {
        $(panel).hide();
        disposeChart();
    }

    // Mirrors UnlistedSymbol::isUnlisted: the trades form's Listed/Unlisted toggle
    // prefixes the symbol, and unlisted symbols have no price history to chart.
    const unlistedPrefix = '{{ config('trades.unlisted') }}';

    function loadSymbol()
    {
        const symbol = ($('#symbol-input').val() || '').trim().toUpperCase();
        if (!symbol || symbol.startsWith(unlistedPrefix)) {
            clearPanel();
            return;
        }

        const myReq = ++reqId;
        $.ajax({
            type: 'GET',
            url: "{{ url('/get-symbol-chart') }}",
            data: { symbol: symbol },
            success: function(data)
            {
                if (myReq !== reqId) {
                    return; // a newer request superseded this one
                }

                $('#scp-symbol-link').text(symbol)
                    .attr('href', 'https://finance.yahoo.com/quote/' + symbol);
                $('#scp-name').html(data.name || '');
                $('#scp-quote-details').html(data.quote_header || '');
                buildRangeBar(data, data.currency || '');

                $('#scp-stale-warning').toggle(!!data.stale);

                const gapWarning = data.gap_warning || '';
                $('#scp-gap-warning').text(gapWarning).toggle(!!gapWarning);

                $(panel).show();
                // Panel visible, so the container has width. baseValue is the open
                // position's average unit cost, so the baseline splits green/red at the
                // same price as the /positions and /watchlist-symbols charts.
                buildChart(data.series, parseFloat(data.base_value) || null);

                // Mark every buy and sell of the symbol, from all accounts: none of
                // these forms is scoped to one, the same as /watchlist-symbols (only
                // /positions narrows the markers to the opened account).
                const series = chartSeries;
                loadTradeMarkers({
                    symbol:     symbol,
                    series:     series,
                    seriesData: data.series,
                    // Drop a late response if the panel was cleared or the form moved
                    // on to another symbol.
                    isCurrent:  function()
                    {
                        return myReq === reqId && chartSeries === series;
                    },
                });
            },
            error: function()
            {
                clearPanel();
            }
        });
    }

    $('#symbol-input').on('blur', loadSymbol);
    $('#get-finance-data').on('click', loadSymbol);
    // The toggle flips both the symbol and the button's visibility; this script is
    // included after the form's own finance script, so by now both are up to date.
    $('#is-listed').on('click', loadSymbol);

    if ($('#symbol-input').val()) {
        loadSymbol();
    }

    window.addEventListener('resize', function()
    {
        if (chart) {
            chart.applyOptions({ width: chartContainer.clientWidth });
        }
    });
});
</script>

<script type="module">
$(document).ready(function()
{
    const panel = document.getElementById('osc-panel-row');
    if (!panel) {
        return;
    }
    const chartContainer = document.getElementById('osc-chart');
    const emptyEl        = document.getElementById('osc-chart-empty');

    let chart  = null;
    let reqId  = 0; // guards against out-of-order responses

    @include('myfinance2::general.scripts.partials.fmt-price')

    function disposeChart()
    {
        if (chart) {
            chart.remove();
            chart = null;
        }
        chartContainer.innerHTML = '';
    }

    function buildChart(series)
    {
        disposeChart();

        if (!series || !series.length) {
            chartContainer.style.display = 'none';
            emptyEl.style.display = 'block';
            return;
        }
        chartContainer.style.display = 'block';
        emptyEl.style.display = 'none';

        chart = LightweightCharts.createChart(chartContainer, {
            width: chartContainer.clientWidth,
            height: 300,
            layout: { attributionLogo: false },
            localization: { priceFormatter: fmtPrice },
            grid: {
                vertLines: { visible: false },
                horzLines: { color: 'rgba(42, 46, 57, 0.2)' },
            },
            rightPriceScale: { borderVisible: false },
            timeScale: { borderVisible: false, timeVisible: false },
        });

        const s = chart.addSeries(LightweightCharts.BaselineSeries, {
            lastValueVisible: true,
            priceFormat: { type: 'custom', minMove: 0.01, formatter: fmtPrice },
            topLineColor: 'rgba( 38, 166, 154, 1)',
            topFillColor1: 'rgba( 38, 166, 154, 0.28)',
            topFillColor2: 'rgba( 38, 166, 154, 0.05)',
            bottomLineColor: 'rgba( 239, 83, 80, 1)',
            bottomFillColor1: 'rgba( 239, 83, 80, 0.05)',
            bottomFillColor2: 'rgba( 239, 83, 80, 0.28)',
        });
        s.setData(series);
        chart.timeScale().fitContent();
    }

    @include('myfinance2::general.scripts.partials.range-bar', ['rangeBarId' => 'osc-range-bar'])

    function clearPanel()
    {
        $(panel).hide();
        disposeChart();
    }

    function loadSymbol()
    {
        const symbol = ($('#symbol-input').val() || '').trim().toUpperCase();
        if (!symbol) {
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

                $('#osc-symbol-link').text(symbol)
                    .attr('href', 'https://finance.yahoo.com/quote/' + symbol);
                $('#osc-name').html(data.name || '');
                $('#osc-quote-details').html(data.quote_header || '');
                buildRangeBar(data, data.currency || '');

                $('#osc-stale-warning').toggle(!!data.stale);

                const gapWarning = data.gap_warning || '';
                $('#osc-gap-warning').text(gapWarning).toggle(!!gapWarning);

                $(panel).show();
                buildChart(data.series); // panel visible, so container has width
            },
            error: function()
            {
                clearPanel();
            }
        });
    }

    $('#symbol-input').on('blur', loadSymbol);
    $('#get-finance-data').on('click', loadSymbol);

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

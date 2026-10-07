{{--
    Shared baseline price chart used by the symbol chart modal (/positions,
    /watchlist-symbols "expand chart") and the orders form chart panel, so both surfaces
    render the same chart: same colours, price formatting, grid and avg-cost baseline.

    Defines createSymbolBaselineChart(container, options) in the including scope; the
    including scope must also provide fmtPrice() (see general.scripts.partials.fmt-price).

    Usage:
        @include('myfinance2::general.scripts.partials.symbol-baseline-chart')

        const built = createSymbolBaselineChart(chartContainer, {
            series: data.series, height: 300, baseValue: avgCost,
        });
--}}
    // Build a baseline area chart for a symbol's daily closes. options:
    //   series    required array of { time, value } points, in ascending time order
    //   height    chart height in px (defaults to 300)
    //   baseValue optional price the baseline splits green/red on (the position's
    //             average unit cost); without it the whole series renders green
    // Returns { chart, series } so the caller can resize, dispose, or attach markers.
    function createSymbolBaselineChart(container, options)
    {
        const chart = LightweightCharts.createChart(container, {
            width: container.clientWidth,
            height: options.height || 300,
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
        if (options.baseValue) {
            seriesProperties.baseValue = { type: 'price', price: options.baseValue };
        }

        const series = chart.addSeries(LightweightCharts.BaselineSeries, seriesProperties);
        series.setData(options.series);
        chart.timeScale().fitContent();

        return { chart: chart, series: series };
    }

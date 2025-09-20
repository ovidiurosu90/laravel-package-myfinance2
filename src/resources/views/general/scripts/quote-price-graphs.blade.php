@inject('ChartsBuilder', 'ovidiuro\myfinance2\App\Services\ChartsBuilder')

<script type="module">

const symbolData = {
@if(count($symbols) > 0)
@foreach($symbols as $symbol)
    '{{ $symbol }}': {!!
        $ChartsBuilder::getChartSymbolAsJsonString($symbol)
    !!},
@endforeach
@endif
};

$(document).ready(function()
{
    $('.chart-symbol').each(function()
    {
        const symbol = $(this).data('symbol');
        const symbolName = $(this).data('symbol_name');
        const baseValue = $(this).data('base_value');
        const tradeCurrencyFormatted = $(this).data('trade_currency_formatted');
        const chartElement = $(this)[0];
        const symbolChart = LightweightCharts.createChart(
            chartElement,
            {
                width: 128,
                height: 25,

                layout: {
                    // background: {
                    //     type: 'solid',
                    //     color: '#000000',
                    // },
                    // textColor: '#d1d4dc',
                    attributionLogo: false,
                },
                grid: {
                    vertLines: {
                        visible: false,
                    },
                    horzLines: {
                        // color: 'rgba(42, 46, 57, 0.5)',
                        visible: false,
                    },
                },
                rightPriceScale: {
                    // borderVisible: false,
                    visible: false,
                },
                timeScale: {
                    visible: false,
                    borderVisible: false,
                },
                crosshair: {
                    horzLine: {
                        visible: false,
                        labelVisible: false,
                    },
                    vertLine: {
                        labelVisible: false,
                    },
                },
            } // end chartOptions
        ); // end createChart

        var seriesProperties = {
            lastValueVisible: false,
            crossHairMarkerVisible: false,
            topLineColor: 'rgba( 38, 166, 154, 1)',
            topFillColor1: 'rgba( 38, 166, 154, 0.28)',
            topFillColor2: 'rgba( 38, 166, 154, 0.05)',
            bottomLineColor: 'rgba( 239, 83, 80, 1)',
            bottomFillColor1: 'rgba( 239, 83, 80, 0.05)',
            bottomFillColor2: 'rgba( 239, 83, 80, 0.28)',
        }; // end seriesProperties

        if (baseValue) {
            seriesProperties.baseValue = { type: 'price', price: baseValue };
        }

        const symbolSeries = symbolChart.addSeries(
            LightweightCharts.BaselineSeries,
            seriesProperties
        );

        symbolSeries.priceScale().applyOptions({
            scaleMargins: {
                // highest point of the series will be 20% away from the top
                top: 0.2,

                // lowest point will be 20% away from the bottom
                bottom: 0.2,
            },
        });

        symbolChart.timeScale().applyOptions({
            rightOffset: 0.08,

            // default is 6 (with current settings shows almost 5m)
            barSpacing: 1.3,
        });

        symbolSeries.setData(symbolData[symbol]);

        const toolTipWidth = 80;
        const toolTipHeight = 80;
        const toolTipMargin = 15;

        // Create and style the tooltip html element
        const toolTip = document.createElement('div');
        toolTip.className = `my-tooltip`;
        toolTip.style = `width: 148px; height: 116px; position: absolute; `
            + `display: none; padding: 8px; box-sizing:border-box; font-size:12px; `
            + `text-align: left; z-index: 1000; top: 12px; left: 12px; `
            + `pointer-events: none; border: 1px solid; border-radius: 2px; `
            + `font-family: -apple-system, BlinkMacSystemFont, 'Trebuchet MS', `
            +     `Roboto, Ubuntu, sans-serif; `
            + `-webkit-font-smoothing: antialiased; `
            + `-moz-osx-font-smoothing: grayscale;`;
        toolTip.style.background = 'white';
        toolTip.style.color = 'black';
        toolTip.style.borderColor = 'rgba( 38, 166, 154, 1)';
        chartElement.appendChild(toolTip);

        // update tooltip
        symbolChart.subscribeCrosshairMove(param =>
        {
            if (
                param.point === undefined ||
                !param.time ||
                param.point.x < 0 ||
                param.point.x > chartElement.clientWidth ||
                param.point.y < 0 ||
                param.point.y > chartElement.clientHeight
            ) {
                toolTip.style.display = 'none';
            } else {
                // time will be in the same format that we supplied to setData.
                // thus it will be YYYY-MM-DD
                const dateStr = param.time;
                toolTip.style.display = 'block';
                const data = symbolData[symbol].find((element) =>
                    element.time == param.time);
                const price = data.value !== undefined ? data.value : data.close;
                toolTip.innerHTML
                    = `<div style="color: ${'rgba( 38, 166, 154, 1)'}">`
                    +     symbolName
                    + `</div>`
                    + `<div style="font-size: 24px; margin: 4px 0px;`
                    + `            color:${'black'}">`
                    +     `${Math.round(100 * price) / 100} `
                    +     tradeCurrencyFormatted
                    + `</div>`
                    + `<div style="color: ${'black'}">${dateStr}</div>`;

                var left = param.point.x + toolTipMargin;
                if (left >
                    chartElement.clientWidth - toolTipWidth
                ) {
                    left = param.point.x - toolTipMargin - toolTipWidth;
                }

                var top = param.point.y + toolTipMargin;
                if (top > chartElement.clientHeight - toolTipHeight) {
                    top = param.point.y - toolTipHeight - toolTipMargin;
                }
                toolTip.style.left = left + 'px';
                toolTip.style.top = top + 'px';
            }
        }); // end symbolChart.subscribeCrosshairMove()

    }); // end $('.chart-symbol').each()

}); // end document.ready()
</script>


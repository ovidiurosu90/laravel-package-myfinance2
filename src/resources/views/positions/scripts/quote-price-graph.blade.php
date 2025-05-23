<script type="module">
$(document).ready(function()
{
    @php $myName = $accountId . '_'
        . preg_replace("/[^a-zA-Z0-9]+/", "", $item['symbol']); @endphp

    const container_{{ $myName }} = document.getElementById(
        'chart-{{ $accountId }}-{{ $item['symbol'] }}');

    const chart_{{ $myName }} = LightweightCharts.createChart(
        container_{{ $myName }},
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
        }); // end createChart()

    const series_{{ $myName }} = chart_{{ $myName }}.addSeries(
        LightweightCharts.BaselineSeries,
        {
            lastValueVisible: false,
            crossHairMarkerVisible: false,
            baseValue: { type: 'price', price: 25 },
            topLineColor: 'rgba( 38, 166, 154, 1)',
            topFillColor1: 'rgba( 38, 166, 154, 0.28)',
            topFillColor2: 'rgba( 38, 166, 154, 0.05)',
            bottomLineColor: 'rgba( 239, 83, 80, 1)',
            bottomFillColor1: 'rgba( 239, 83, 80, 0.05)',
            bottomFillColor2: 'rgba( 239, 83, 80, 0.28)',
        });
    const seriesData_{{ $myName }} = [

    @if(!empty($item['stats']['historical']))
        @foreach($item['stats']['historical'] as $stat)
        { time: '{{ $stat['date'] }}', value: {{ $stat['unit_price'] }} },
        @endforeach
    @endif

    @if(!empty($item['stats']['today_last']))
        { time: '{{ date('Y-m-d') }}', value: {{
            $item['stats']['today_last']['unit_price'] }} },
    @endif

    ];

    series_{{ $myName }}.priceScale().applyOptions({
        scaleMargins: {
            top: 0.2, // highest point of the series will be 20% away from the top
            bottom: 0.2, // lowest point will be 20% away from the bottom
        },
    });
    chart_{{ $myName }}.timeScale().applyOptions({
        rightOffset: 0.08,
        barSpacing: 1.3, // default is 6 (with current settings shows almost 5m)
    });

    series_{{ $myName }}.setData(seriesData_{{ $myName }});


    const toolTipWidth_{{ $myName }} = 80;
    const toolTipHeight_{{ $myName }} = 80;
    const toolTipMargin_{{ $myName }} = 15;

    // Create and style the tooltip html element
    const toolTip_{{ $myName }} = document.createElement('div');
    toolTip_{{ $myName }}.className = `my-tooltip`;
    toolTip_{{ $myName }}.style = `width: 138px; height: 116px; position: absolute;`
        + `display: none; padding: 8px; box-sizing: border-box; font-size: 12px; `
        + `text-align: left; z-index: 1000; top: 12px; left: 12px; `
        + `pointer-events: none; border: 1px solid; border-radius: 2px; `
        + `font-family: -apple-system, BlinkMacSystemFont, 'Trebuchet MS', Roboto, `
        +     `Ubuntu, sans-serif;`
        + `-webkit-font-smoothing: antialiased; `
        + `-moz-osx-font-smoothing: grayscale;`;
    toolTip_{{ $myName }}.style.background = 'white';
    toolTip_{{ $myName }}.style.color = 'black';
    toolTip_{{ $myName }}.style.borderColor = 'rgba( 38, 166, 154, 1)';
    container_{{ $myName }}.appendChild(toolTip_{{ $myName }});

    // update tooltip
    chart_{{ $myName }}.subscribeCrosshairMove(param =>
    {
        if (
            param.point === undefined ||
            !param.time ||
            param.point.x < 0 ||
            param.point.x > container_{{ $myName }}.clientWidth ||
            param.point.y < 0 ||
            param.point.y > container_{{ $myName }}.clientHeight
        ) {
            toolTip_{{ $myName }}.style.display = 'none';
        } else {
            // time will be in the same format that we supplied to setData.
            // thus it will be YYYY-MM-DD
            const dateStr = param.time;
            toolTip_{{ $myName }}.style.display = 'block';
            const data = seriesData_{{ $myName }}.find((element) =>
                element.time == param.time);
            const price = data.value !== undefined ? data.value : data.close;
            toolTip_{{ $myName }}.innerHTML
                = `<div style="color: ${'rgba( 38, 166, 154, 1)'}">`
                +     `{{ $item['symbol_name'] }}`
                + `</div>`
                + `<div style="font-size: 24px; margin: 4px 0px; color:${'black'}">`
                +     `${Math.round(100 * price) / 100} `
                +     `{!! $item['tradeCurrencyModel']->display_code !!}`
                + `</div>`
                + `<div style="color: ${'black'}">${dateStr}</div>`;

            var left_{{ $myName }} = param.point.x + toolTipMargin_{{ $myName }};
            if (left_{{ $myName }} >
                container_{{ $myName }}.clientWidth - toolTipWidth_{{ $myName }}
            ) {
                left_{{ $myName }} = param.point.x - toolTipMargin_{{ $myName }}
                    - toolTipWidth_{{ $myName }};
            }

            var top_{{ $myName }} = param.point.y + toolTipMargin_{{ $myName }};
            if (top_{{ $myName }} >
                container_{{ $myName }}.clientHeight - toolTipHeight_{{ $myName }}
            ) {
                top_{{ $myName }} = param.point.y - toolTipHeight_{{ $myName }}
                    - toolTipMargin_{{ $myName }};
            }
            toolTip_{{ $myName }}.style.left = left_{{ $myName }} + 'px';
            toolTip_{{ $myName }}.style.top = top_{{ $myName }} + 'px';
        }
    }); // end chart.subscribeCrosshairMove()

}); // end document.ready()
</script>


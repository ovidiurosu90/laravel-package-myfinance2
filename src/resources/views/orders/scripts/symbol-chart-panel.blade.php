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

    // 52-week range bar: current price between the 52W low/high, with the
    // distance from each end in the tooltip and a compact caption.
    function buildRangeBar(data, cur)
    {
        const high  = data.fiftyTwoWeekHigh;
        const low   = data.fiftyTwoWeekLow;
        const price = data.price;
        const $bar  = $('#osc-range-bar');

        if (high == null || low == null || price == null || high <= low) {
            $bar.html('');
            return;
        }

        let pos = ((price - low) / (high - low)) * 100;
        pos = Math.max(0, Math.min(100, pos));

        const belowHigh = data.fiftyTwoWeekHighChangePercent != null
            ? (-data.fiftyTwoWeekHighChangePercent * 100).toFixed(2) : null;
        const aboveLow = data.fiftyTwoWeekLowChangePercent != null
            ? (data.fiftyTwoWeekLowChangePercent * 100).toFixed(2) : null;

        const lowLabel   = fmtPrice(low) + ' ' + cur;
        const highLabel  = fmtPrice(high) + ' ' + cur;
        const priceLabel = fmtPrice(price) + ' ' + cur;

        const tip = [
            belowHigh != null ? belowHigh + '% below 52W high' : '',
            aboveLow != null ? aboveLow + '% above 52W low' : '',
        ].filter(Boolean).join(' · ');

        const caption = [
            belowHigh != null ? '▼ ' + belowHigh + '% high' : '',
            aboveLow != null ? '▲ ' + aboveLow + '% low' : '',
        ].filter(Boolean).join(' · ');

        $bar.html(
            '<div style="padding-top:20px;">'
          +   '<div style="display:flex;align-items:center;gap:4px;'
          +       'font-size:0.72rem;color:#000;">'
          +     '<span style="white-space:nowrap;">' + lowLabel + '</span>'
          +     '<div style="flex:1;position:relative;height:5px;background:#e0e0e0;'
          +         'border-radius:3px;min-width:60px;">'
          +       '<span style="position:absolute;left:' + pos + '%;'
          +           'transform:translateX(-50%);bottom:calc(100% + 5px);'
          +           'font-size:0.875rem;white-space:nowrap;color:#555;">'
          +         priceLabel + '</span>'
          +       '<div data-tooltip="' + tip + '" style="position:absolute;'
          +           'width:8px;height:8px;background:#555;border-radius:1px;'
          +           'top:-1.5px;transform:translateX(-50%);left:' + pos + '%;"></div>'
          +     '</div>'
          +     '<span style="white-space:nowrap;">' + highLabel + '</span>'
          +   '</div>'
          +   '<div style="font-size:0.68rem;color:#6c757d;text-align:center;'
          +       'margin-top:6px;white-space:nowrap;">'
          +     '52W Range&nbsp;&nbsp;' + caption + '</div>'
          + '</div>'
        );
    }

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

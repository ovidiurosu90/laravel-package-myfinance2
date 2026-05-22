@inject('ChartsBuilder', 'ovidiuro\myfinance2\App\Services\ChartsBuilder')

<script type="module">

@include('myfinance2::positions.scripts._formatters')

// Load user-level metric data for all metrics and both currencies (EUR, USD).
// Each metric has separate data for EUR and USD views because changePercentage is
// calculated per currency from aggregated account stats across all accounts.
// Data is precomputed by FinanceApiCron and stored as JSON files.
const userOverviewData = {
@foreach(['EUR', 'USD'] as $currency)
    @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
        '{{ $metric . '_' . $currency }}': {!!
            $ChartsBuilder::getChartOverviewUserAsJsonString(Auth::user()->id,
                $metric . '_' . $currency)
        !!},
    @endforeach
@endforeach
};

const currencyExchangeData = {!!
    $ChartsBuilder::getChartSymbolAsJsonString('EURUSD=X')
!!};

$(document).ready(function()
{
    var $element = $('#chart-userOverview');
    const chartElement = $element[0];
    // Create chart with dual price scales:
    // - Left scale: for changePercentage (aggregated across all user accounts)
    // - Right scale: for other metrics aggregated from all user accounts (EUR/USD)
    const userOverviewChart = LightweightCharts.createChart(
        chartElement,
        {
            width: chartElement.clientWidth,
            height: 250,
            layout: {
                attributionLogo: false,
            },
            leftPriceScale: {
                visible: true,
            },
            rightPriceScale: {
                visible: true,
            },
        } // end chartOptions
    ); // end createChart

    function setLocalization()
    {
        // Re-apply formatters when currency changes (called from toggle handler)
        const currency = $element.data('currency_iso_code');

        // Update priceFormat for all currency series (cost, change, mvalue, cash)
        @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
        @if($metric !== 'changePercentage')
        series_{{ $metric }}.applyOptions({
            priceFormat: {
                type: 'custom',
                minMove: 0.01,
                formatter: (price) => {
                    return currencyFormatter_instance(price, currency);
                },
            },
        });
        @endif
        @endforeach
    }

    // Helper: Get last value from stats array
    function getLastStatValue(statsArray) {
        if (!statsArray || statsArray.length === 0) {
            return null;
        }
        return statsArray[statsArray.length - 1].value;
    }

    // Helper: Format and display metric status
    function displayMetricStatus(metric, value, color) {
        const $element = $('#' + metric + '-status');
        $element.css('color', color);
        $element.html(value + " " + metric);
    }

    // Helper: Display metric percentage based on metric type
    function displayMetricPercentage(metric, color, data) {
        const $element = $('#' + metric + '-status-percentage');
        $element.css('color', color);

        let percentage;
        switch(metric) {
            case 'cost':
                percentage = '-100%';
                break;
            case 'mvalue':
                percentage = '+' + (Math.round(100 * data.mvalue / data.cost * 100) / 100)
                             + '%';
                break;
            case 'change':
                // Use pre-calculated changePercentage metric
                percentage = '&nbsp;&nbsp;&nbsp;'
                    + (Math.round(data.changePercentage * 100) / 100) + '%';
                break;
            case 'cash':
                percentage = (Math.round(100 * data.cash / data.cost * 100) / 100) + '%';
                break;
            default:
                percentage = '-';
        }
        $element.html(percentage);
    }

    function formatStatusCurrency(value, currencyCode)
    {
        const decimals = Math.abs(value) >= 1000 ? 0 : 2;
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currencyCode,
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(value);
    }

    const metricColors = {
        @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
        '{{ $metric }}': '{{ $properties["line_color"] }}',
        @endforeach
    };

    function computePercentageRangeMinMax(numeratorKey, denominatorKey, currency)
    {
        const numArray = userOverviewData[numeratorKey + '_' + currency] || [];
        const denArray = userOverviewData[denominatorKey + '_' + currency] || [];
        const denByDate = {};
        denArray.forEach((d) => { denByDate[d.time] = d.value; });
        let min = null;
        let max = null;
        numArray.forEach((d) =>
        {
            const den = denByDate[d.time];
            if (den && den !== 0) {
                const pct = (d.value / den) * 100;
                if (min === null || pct < min) min = pct;
                if (max === null || pct > max) max = pct;
            }
        });
        return { min, max };
    }

    function computeDirectRangeMinMax(key, currency)
    {
        const arr = userOverviewData[key + '_' + currency] || [];
        let min = null;
        let max = null;
        arr.forEach((d) =>
        {
            if (min === null || d.value < min) min = d.value;
            if (max === null || d.value > max) max = d.value;
        });
        return { min, max };
    }

    function renderRangeBar(elementId, min, max, current, currentLabel, labelColor,
        minMaxFormatter, thresholds)
    {
        if (min === null || max === null || current === null) return;
        const range = max - min;
        const position = range === 0
            ? 50 : Math.max(0, Math.min(100, ((current - min) / range) * 100));
        const fmt = minMaxFormatter !== undefined
            ? minMaxFormatter : (v) => (Math.round(v * 100) / 100) + '%';
        let thresholdHtml = '';
        if (thresholds && range !== 0) {
            thresholds.forEach((t) =>
            {
                const pos = Math.max(0, Math.min(100, ((t.value - min) / range) * 100));
                thresholdHtml += '<div data-tooltip="' + (t.title || '') + '" style="position:absolute;'
                    + 'width:12px;height:11px;background:transparent;top:-3px;left:' + pos
                    + '%;transform:translateX(-50%);display:flex;justify-content:center;">'
                    + '<div style="width:2px;height:100%;background:' + t.color
                    + ';border-radius:1px;opacity:0.85;"></div>'
                    + '</div>';
            });
        }
        $('#' + elementId).html(
            '<div style="padding-top:20px;">'
            + '<div style="display:flex;align-items:center;gap:4px;font-size:0.72rem;'
            + 'color:#000;">'
            + '<span style="white-space:nowrap;">' + fmt(min) + '</span>'
            + '<div style="flex:1;position:relative;height:5px;background:#e0e0e0;'
            + 'border-radius:3px;min-width:40px;">'
            + '<span style="position:absolute;left:' + position
            + '%;transform:translateX(-50%);bottom:calc(100% + 5px);font-size:0.875rem;'
            + 'white-space:nowrap;color:' + labelColor + ';">'
            + currentLabel + '</span>'
            + thresholdHtml
            + '<div style="position:absolute;width:8px;height:8px;background:#555;'
            + 'border-radius:1px;top:-1.5px;transform:translateX(-50%);left:'
            + position + '%;"></div>'
            + '</div>'
            + '<span style="white-space:nowrap;">' + fmt(max) + '</span>'
            + '</div>'
            + '</div>'
        );
    }

    function setStatus()
    {
        const currency = $element.data('currency_iso_code');

        // Get last values from all metrics for status display
        const statusData = {
            mvalue: getLastStatValue(userOverviewData['mvalue_' + currency]),
            cost: getLastStatValue(userOverviewData['cost_' + currency]),
            change: getLastStatValue(userOverviewData['change_' + currency]),
            cash: getLastStatValue(userOverviewData['cash_' + currency]),
            changePercentage: getLastStatValue(
                userOverviewData['changePercentage_' + currency]),
        };

        // Only display status if we have data
        if (statusData.cost === null) {
            return; // No data to display yet
        }

        @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
        @if($metric !== 'changePercentage')
        const {{ $metric }}Value = formatStatusCurrency(statusData.{{ $metric }},
            $element.data('currency_iso_code'));

        displayMetricStatus('{{ $metric }}',
            {{ $metric }}Value, '{{ $properties["line_color"] }}');
        displayMetricPercentage('{{ $metric }}',
            '{{ $properties["line_color"] }}', statusData);
        @endif
        @endforeach

        $('#mvalue-status-percentage').html('');
        $('#change-status-percentage').html('');
        $('#cash-status-percentage').html('');

        renderRangeBar('cost-range-bar', 0, 100, 50, '-100%', metricColors.cost, () => '');

        const mvalueRange = computePercentageRangeMinMax('mvalue', 'cost', currency);
        const mvaluePct = statusData.cost !== 0
            ? (statusData.mvalue / statusData.cost) * 100 : null;
        const mvaluePctLabel = mvaluePct !== null
            ? '+' + (Math.round(mvaluePct * 100) / 100) + '%' : null;
        renderRangeBar('mvalue-range-bar', mvalueRange.min, mvalueRange.max, mvaluePct,
            mvaluePctLabel, metricColors.mvalue);

        const changeRange = computeDirectRangeMinMax('changePercentage', currency);
        const changePctLabel = statusData.changePercentage !== null
            ? (Math.round(statusData.changePercentage * 100) / 100) + '%' : null;
        renderRangeBar('change-range-bar', changeRange.min, changeRange.max,
            statusData.changePercentage, changePctLabel, metricColors.changePercentage);

        const cashRange = computePercentageRangeMinMax('cash', 'cost', currency);
        const cashPct = statusData.cost !== 0
            ? (statusData.cash / statusData.cost) * 100 : null;
        const cashPctLabel = cashPct !== null
            ? (Math.round(cashPct * 100) / 100) + '%' : null;
        renderRangeBar('cash-range-bar', cashRange.min, cashRange.max, cashPct,
            cashPctLabel, metricColors.cash);

        // Populate collapsed header summary
        const uosCost = document.getElementById('uos-cost');
        if (uosCost) {
            uosCost.innerHTML = '<span style="color:' + metricColors.cost + '">-'
                + formatStatusCurrency(statusData.cost, currency) + ' cost</span>';
        }
        const uosMvalue = document.getElementById('uos-mvalue');
        if (uosMvalue) {
            uosMvalue.innerHTML = '<span style="color:' + metricColors.mvalue + '">+'
                + formatStatusCurrency(statusData.mvalue, currency) + ' mvalue</span>';
        }
        const uosChange = document.getElementById('uos-change');
        if (uosChange) {
            const changePct = statusData.changePercentage !== null
                ? ' <span style="color:' + metricColors.changePercentage + '">('
                    + (Math.round(statusData.changePercentage * 100) / 100) + '%)</span>'
                : '';
            uosChange.innerHTML = '<span style="color:' + metricColors.change + '">'
                + formatStatusCurrency(statusData.change, currency) + ' change</span>' + changePct;
        }
        const uosCash = document.getElementById('uos-cash');
        if (uosCash) {
            uosCash.innerHTML = '<span style="color:' + metricColors.cash + '">'
                + formatStatusCurrency(statusData.cash, currency) + ' cash</span>';
        }
    }

    // Display currency exchange rate if data exists
    if (currencyExchangeData && currencyExchangeData.length > 0) {
        const currencyExchangeLast = currencyExchangeData[currencyExchangeData.length - 1];
        var $currencyExchangeElement = $('#currency_exchange-status');
        $currencyExchangeElement.html("EURUSD " + currencyExchangeLast.time);

        const eurusdRate = parseFloat(currencyExchangeLast.value);
        const buyUsdAbove = {{ config('trades.eurusd_thresholds.buy_usd_above') }};
        const sellUsdBelow = {{ config('trades.eurusd_thresholds.sell_usd_below') }};
        if (eurusdRate > buyUsdAbove) {
            $('#eurusd-signal').html('<span class="badge bg-success">buy $</span>');
        } else if (eurusdRate < sellUsdBelow) {
            $('#eurusd-signal').html('<span class="badge bg-danger">sell $</span>');
        }

        // Render EURUSD all-time range bar with buy/sell threshold markers
        let eurusdMin = null;
        let eurusdMax = null;
        currencyExchangeData.forEach((d) =>
        {
            const v = parseFloat(d.value);
            if (eurusdMin === null || v < eurusdMin) eurusdMin = v;
            if (eurusdMax === null || v > eurusdMax) eurusdMax = v;
        });
        const eurusdFmt = (v) => (Math.round(v * 10000) / 10000).toFixed(4);
        $('#uos-eurusd').text('EURUSD ' + eurusdFmt(eurusdRate));
        const eurusdThresholds = [
            { value: buyUsdAbove, color: '#198754',
                title: 'Buy USD above ' + eurusdFmt(buyUsdAbove) },
            { value: sellUsdBelow, color: '#dc3545',
                title: 'Sell USD below ' + eurusdFmt(sellUsdBelow) },
        ];
        renderRangeBar('eurusd-range-bar', eurusdMin, eurusdMax,
            eurusdRate, eurusdFmt(eurusdRate), '#555', eurusdFmt, eurusdThresholds);
        document.querySelectorAll('#eurusd-range-bar [data-tooltip]').forEach((el) =>
        {
            const text = el.getAttribute('data-tooltip');
            let tip = null;
            el.addEventListener('mouseenter', () =>
            {
                tip = document.createElement('div');
                tip.textContent = text;
                tip.style.cssText = 'position:fixed;background:#000;color:#fff;padding:4px 8px;'
                    + 'border-radius:4px;font-size:0.75rem;z-index:9999;pointer-events:none;';
                document.body.appendChild(tip);
                const r = el.getBoundingClientRect();
                tip.style.left = (r.left + r.width / 2 - tip.offsetWidth / 2) + 'px';
                tip.style.top = (r.top - tip.offsetHeight - 5) + 'px';
            });
            el.addEventListener('mouseleave', () => { if (tip) { tip.remove(); tip = null; } });
        });
    }

    // Create formatter instances for all series
    const currencyFormatter_instance = createCurrencyFormatter();
    const percentageFormatter_instance = createPercentageFormatter();

    @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
    @if($metric === 'changePercentage')
    // changePercentage uses left scale (percentage)
    const series_{{ $metric }} = userOverviewChart.addSeries(
        LightweightCharts.BaselineSeries,
    {
        lineColor: '{{ $properties['line_color'] }}',
        topLineColor: '{{ $properties['line_color'] }}',
        bottomLineColor: '{{ $properties['line_color'] }}',
        lineStyle: {{ $properties['line_style'] }},
        priceScaleId: 'left',
        priceFormat: {
            type: 'custom',
            minMove: 0.01,
            formatter: (price) => percentageFormatter_instance(price),
        },
    });
    @else
    // Other metrics use right scale (currency)
    const series_{{ $metric }} = userOverviewChart.addSeries(
        LightweightCharts.BaselineSeries,
    {
        lineColor: '{{ $properties['line_color'] }}',
        topLineColor: '{{ $properties['line_color'] }}',
        bottomLineColor: '{{ $properties['line_color'] }}',
        lineStyle: {{ $properties['line_style'] }},
        priceFormat: {
            type: 'custom',
            minMove: 0.01,
            formatter: (price) => currencyFormatter_instance(price,
                                    $element.data('currency_iso_code')),
        },
    });
    @endif
    const data_{{ $metric }} = userOverviewData['{{ $metric }}_'
        + ($element.data('currencyIsoCode') || $element.data('currency_iso_code')
            || 'EUR')];
    series_{{ $metric }}.setData(data_{{ $metric }});
    @endforeach

    // Apply scale margins to improve readability
    userOverviewChart.priceScale('right').applyOptions({
        scaleMargins: {
            top: 0.1,
            bottom: 0.1,
        },
    });

    userOverviewChart.priceScale('left').applyOptions({
        scaleMargins: {
            top: 0.3,
            bottom: 0.3,
        },
    });

    // Update status display with metric values
    setStatus();

    // Legend toggle: click a badge to show/hide its series
    const seriesVisible = {
        @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
        '{{ $metric }}': true,
        @endforeach
    };

    @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
    $('#legend-{{ $metric }}').on('click', function()
    {
        seriesVisible['{{ $metric }}'] = !seriesVisible['{{ $metric }}'];
        series_{{ $metric }}.applyOptions({ visible: seriesVisible['{{ $metric }}'] });
        $(this).css('opacity', seriesVisible['{{ $metric }}'] ? '1' : '0.3');
    });
    @endforeach

    // Zoom chart to the last N days (0 = fit all)
    function zoomChart(days)
    {
        const timeScale = userOverviewChart.timeScale();
        if (days === 0) {
            timeScale.fitContent();
            return;
        }
        const currency = $element.data('currency_iso_code');
        const allData = userOverviewData['cost_' + currency] || [];
        if (allData.length === 0) return;
        const lastEntry = allData[allData.length - 1];
        const lastDate = new Date(lastEntry.time + 'T00:00:00');
        const toDate = new Date(lastDate);
        toDate.setDate(toDate.getDate() + 5);
        const fromDate = new Date(lastDate);
        fromDate.setDate(fromDate.getDate() - days);
        timeScale.setVisibleRange({
            from: fromDate.toISOString().split('T')[0],
            to: toDate.toISOString().split('T')[0],
        });
    }

    zoomChart(365);

    requestAnimationFrame(() =>
    {
        $('#legend-changePercentage').css('margin-left',
            (userOverviewChart.priceScale('left').width() - 2) + 'px');
        $('#legend-right-badges').css('margin-right',
            userOverviewChart.priceScale('right').width() + 'px');
    });

    $('.zoom-btn').on('click', function()
    {
        $('.zoom-btn').removeClass('active');
        $(this).addClass('active');
        zoomChart(parseInt($(this).data('days')));
    });

    // Helper: Update chart and UI when currency changes
    function updateChartForCurrency(newCurrency)
    {
        // Update UI state
        $element.data('currency_iso_code', newCurrency);
        const url = new URL(window.location.href);
        url.searchParams.set('currency_iso_code', newCurrency);
        window.history.replaceState(null, null, url);

        // Update formatters and status
        setLocalization();
        setStatus();

        // Update all series with new currency data
        @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
        series_{{ $metric }}.setData(userOverviewData['{{ $metric }}_' + newCurrency]);
        @endforeach
    }

    // Handle currency toggle (EUR <-> USD)
    $("#toggle-currency-select").change(function()
    {
        const newCurrency = $(this).is(':checked') ? 'EUR' : 'USD';
        updateChartForCurrency(newCurrency);
    });

    $('#user-overview').on('shown.bs.collapse', function()
    {
        userOverviewChart.resize(chartElement.clientWidth, 250);
    });

    $(window).on('resize', function()
    {
        userOverviewChart.resize(chartElement.clientWidth, 250);
    });

}); // end document.ready()
</script>


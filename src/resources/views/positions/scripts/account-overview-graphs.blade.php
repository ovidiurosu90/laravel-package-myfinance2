@inject('ChartsBuilder', 'ovidiuro\myfinance2\App\Services\ChartsBuilder')

<script type="module">

@include('myfinance2::positions.scripts._formatters')

// Load all metric data for each account, including changePercentage which is a
// derived metric (change / cost * 100). Data is precomputed by FinanceApiCron
// and stored as JSON files containing historical and today_last data points.
const metricData = {
@foreach($accountData as $accountId => $value)
    '{{ $accountId }}': {
    @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
        '{{ $metric }}': {!!
            $ChartsBuilder::getChartAccountAsJsonString($accountData[$accountId],
                                                        $metric)
        !!},
    @endforeach
    },
@endforeach
};

const metricColors = {
    @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
    '{{ $metric }}': '{{ $properties["line_color"] }}',
    @endforeach
};

$(document).ready(function()
{
    function getLastStatValue(statsArray)
    {
        if (!statsArray || statsArray.length === 0) {
            return null;
        }
        return statsArray[statsArray.length - 1].value;
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

    function computePercentageRangeMinMax(accountId, numeratorKey, denominatorKey)
    {
        const numArray = metricData[accountId][numeratorKey] || [];
        const denArray = metricData[accountId][denominatorKey] || [];
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

    function computeDirectRangeMinMax(accountId, key)
    {
        const arr = metricData[accountId][key] || [];
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
        minMaxFormatter)
    {
        if (min === null || max === null || current === null) return;
        const range = max - min;
        const position = range === 0
            ? 50 : Math.max(0, Math.min(100, ((current - min) / range) * 100));
        const fmt = minMaxFormatter !== undefined
            ? minMaxFormatter : (v) => (Math.round(v * 100) / 100) + '%';
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
            + '<div style="position:absolute;width:8px;height:8px;background:#555;'
            + 'border-radius:1px;top:-1.5px;transform:translateX(-50%);left:'
            + position + '%;"></div>'
            + '</div>'
            + '<span style="white-space:nowrap;">' + fmt(max) + '</span>'
            + '</div>'
            + '</div>'
        );
    }

    function setAccountStatus(accountId, currency)
    {
        const statusData = {
            mvalue: getLastStatValue(metricData[accountId]['mvalue']),
            cost: getLastStatValue(metricData[accountId]['cost']),
            change: getLastStatValue(metricData[accountId]['change']),
            cash: getLastStatValue(metricData[accountId]['cash']),
            changePercentage: getLastStatValue(metricData[accountId]['changePercentage']),
        };

        if (statusData.cost === null) return;

        @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
        @if($metric !== 'changePercentage')
        {
            const value = formatStatusCurrency(statusData.{{ $metric }}, currency);
            const $el = $('#{{ $metric }}-status-' + accountId);
            $el.css('color', metricColors['{{ $metric }}']);
            $el.html(value + ' {{ $metric }}');
        }
        @endif
        @endforeach

        const aosCost = document.getElementById('aos-cost-' + accountId);
        if (aosCost) {
            aosCost.innerHTML = '<span style="color:' + metricColors.cost + '">-'
                + formatStatusCurrency(statusData.cost, currency) + ' cost</span>';
        }
        const aosMvalue = document.getElementById('aos-mvalue-' + accountId);
        if (aosMvalue) {
            aosMvalue.innerHTML = '<span style="color:' + metricColors.mvalue + '">+'
                + formatStatusCurrency(statusData.mvalue, currency) + ' mvalue</span>';
        }
        const aosChange = document.getElementById('aos-change-' + accountId);
        if (aosChange) {
            const changePct = statusData.changePercentage !== null
                ? ' <span style="color:' + metricColors.changePercentage + '">('
                    + (Math.round(statusData.changePercentage * 100) / 100) + '%)</span>'
                : '';
            aosChange.innerHTML = '<span style="color:' + metricColors.change + '">'
                + formatStatusCurrency(statusData.change, currency)
                + ' change</span>' + changePct;
        }
        const aosCash = document.getElementById('aos-cash-' + accountId);
        if (aosCash) {
            aosCash.innerHTML = '<span style="color:' + metricColors.cash + '">'
                + formatStatusCurrency(statusData.cash, currency) + ' cash</span>';
        }

        renderRangeBar('cost-range-bar-' + accountId, 0, 100, 50, '-100%',
            metricColors.cost, () => '');

        const mvalueRange = computePercentageRangeMinMax(accountId, 'mvalue', 'cost');
        const mvaluePct = statusData.cost !== 0
            ? (statusData.mvalue / statusData.cost) * 100 : null;
        const mvaluePctLabel = mvaluePct !== null
            ? '+' + (Math.round(mvaluePct * 100) / 100) + '%' : null;
        renderRangeBar('mvalue-range-bar-' + accountId, mvalueRange.min, mvalueRange.max,
            mvaluePct, mvaluePctLabel, metricColors.mvalue);

        const changeRange = computeDirectRangeMinMax(accountId, 'changePercentage');
        const changePctLabel = statusData.changePercentage !== null
            ? (Math.round(statusData.changePercentage * 100) / 100) + '%' : null;
        renderRangeBar('change-range-bar-' + accountId, changeRange.min, changeRange.max,
            statusData.changePercentage, changePctLabel, metricColors.changePercentage);

        const cashRange = computePercentageRangeMinMax(accountId, 'cash', 'cost');
        const cashPct = statusData.cost !== 0
            ? (statusData.cash / statusData.cost) * 100 : null;
        const cashPctLabel = cashPct !== null
            ? (Math.round(cashPct * 100) / 100) + '%' : null;
        renderRangeBar('cash-range-bar-' + accountId, cashRange.min, cashRange.max,
            cashPct, cashPctLabel, metricColors.cash);
    }

    const accountCharts = {};

    $('.chart-accountOverview').each(function()
    {
        const accountId = $(this).data('account_id');
        const chartElement = $(this)[0];
        const currency = $(this).data('account_currency_iso_code');

        const accountOverviewChart = LightweightCharts.createChart(
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
            }
        );

        const currencyFormatter_account = createCurrencyFormatter();
        const percentageFormatter_account = createPercentageFormatter();

        @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
        @if($metric === 'changePercentage')
        const series_{{ $metric }} = accountOverviewChart.addSeries(
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
                    formatter: (price) => percentageFormatter_account(price),
                },
            }
        );
        @else
        const series_{{ $metric }} = accountOverviewChart.addSeries(
            LightweightCharts.BaselineSeries,
            {
                lineColor: '{{ $properties['line_color'] }}',
                topLineColor: '{{ $properties['line_color'] }}',
                bottomLineColor: '{{ $properties['line_color'] }}',
                lineStyle: {{ $properties['line_style'] }},
                priceFormat: {
                    type: 'custom',
                    minMove: 0.01,
                    formatter: (price) => currencyFormatter_account(price, currency),
                },
            }
        );
        @endif
        series_{{ $metric }}.setData(metricData[accountId]['{{ $metric }}']);
        @endforeach

        accountOverviewChart.priceScale('right').applyOptions({
            scaleMargins: {
                top: 0.1,
                bottom: 0.1,
            },
        });
        accountOverviewChart.priceScale('left').applyOptions({
            scaleMargins: {
                top: 0.3,
                bottom: 0.3,
            },
        });

        accountCharts[accountId] = { chart: accountOverviewChart, el: chartElement };

        setAccountStatus(accountId, currency);

        const seriesVisible = {
            @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
            '{{ $metric }}': true,
            @endforeach
        };

        @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
        $('#legend-{{ $metric }}-' + accountId).on('click', function()
        {
            seriesVisible['{{ $metric }}'] = !seriesVisible['{{ $metric }}'];
            series_{{ $metric }}.applyOptions({ visible: seriesVisible['{{ $metric }}'] });
            $(this).css('opacity', seriesVisible['{{ $metric }}'] ? '1' : '0.3');
        });
        @endforeach

        function zoomChart(days)
        {
            const timeScale = accountOverviewChart.timeScale();
            if (days === 0) {
                timeScale.fitContent();
                return;
            }
            const allData = metricData[accountId]['cost'] || [];
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
            $('#legend-changePercentage-' + accountId).css('margin-left',
                (accountOverviewChart.priceScale('left').width() - 2) + 'px');
            $('#legend-right-badges-' + accountId).css('margin-right',
                accountOverviewChart.priceScale('right').width() + 'px');
        });

        $('.zoom-btn-account[data-account_id="' + accountId + '"]').on('click', function()
        {
            $('.zoom-btn-account[data-account_id="' + accountId + '"]').removeClass('active');
            $(this).addClass('active');
            zoomChart(parseInt($(this).data('days')));
        });

    }); // end $('.chart-accountOverview').each()

    $(window).on('resize', function()
    {
        Object.values(accountCharts).forEach(function(data)
        {
            data.chart.resize(data.el.clientWidth, 250);
        });
    });

    @foreach($accountData as $accountId => $value)
    {
        const $overview = $('#account-overview-{{ $accountId }}');
        const $summary = $('#account-overview-summary-{{ $accountId }}');
        // DISABLED: keep summary always visible (re-enable to hide it when card is expanded)
        // $overview.on('hide.bs.collapse', function ()
        // {
        //     $summary.removeClass('d-none');
        // }).on('show.bs.collapse', function ()
        // {
        //     $summary.addClass('d-none');
        // });
        $overview.on('shown.bs.collapse', function ()
        {
            const data = accountCharts['{{ $accountId }}'];
            if (data) data.chart.resize(data.el.clientWidth, 250);
        });
    }
    @endforeach

}); // end document.ready()
</script>

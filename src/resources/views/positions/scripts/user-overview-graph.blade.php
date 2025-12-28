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
            width: chartElement.clientWidth, // - 14,
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
        const {{ $metric }}Value = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: $element.data('currency_iso_code'),
        }).format(statusData.{{ $metric }});

        displayMetricStatus('{{ $metric }}',
            {{ $metric }}Value, '{{ $properties["line_color"] }}');
        displayMetricPercentage('{{ $metric }}',
            '{{ $properties["line_color"] }}', statusData);
        @endif
        @endforeach
    }

    // Display currency exchange rate if data exists
    if (currencyExchangeData && currencyExchangeData.length > 0) {
        const currencyExchangeLast = currencyExchangeData[currencyExchangeData.length - 1];
        var $currencyExchangeElement = $('#currency_exchange-status');
        $currencyExchangeElement.html("EURUSD " + currencyExchangeLast.value);
        $currencyExchangeElement.attr('title', currencyExchangeLast.time);
        $('#currency_exchange-status-time').html(currencyExchangeLast.time);
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
        title: '{{ $properties['title'] }}',
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
        title: '{{ $properties['title'] }}',
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

}); // end document.ready()
</script>


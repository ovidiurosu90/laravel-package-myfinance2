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

$(document).ready(function()
{
    $('.chart-accountOverview').each(function()
    {
        const accountId = $(this).data('account_id');
        const chartElement = $(this)[0];
        // Create chart with dual price scales:
        // - Left scale: for changePercentage (0-100% range)
        // - Right scale: for other metrics in currency values (EUR/USD)
        // This allows displaying metrics with different units on the same chart
        const accountOverviewChart = LightweightCharts.createChart(
            chartElement,
            {
                width: chartElement.clientWidth - 30,
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

        // Create formatter instances for this chart
        const currencyFormatter_account = createCurrencyFormatter();
        const percentageFormatter_account = createPercentageFormatter();
        const currency = $(this).data('account_currency_iso_code');

        @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
        @if($metric === 'changePercentage')
        // changePercentage uses the left price scale (percentage scale)
        const series_{{ $metric }} = accountOverviewChart.addSeries(
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
                    formatter: (price) => percentageFormatter_account(price),
                },
            }
        );
        @else
        // Other metrics use the right price scale (currency scale)
        const series_{{ $metric }} = accountOverviewChart.addSeries(
            LightweightCharts.BaselineSeries,
            {
                lineColor: '{{ $properties['line_color'] }}',
                topLineColor: '{{ $properties['line_color'] }}',
                bottomLineColor: '{{ $properties['line_color'] }}',
                title: '{{ $properties['title'] }}',
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

        // Apply scale margins to improve readability
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

    }); // end $('.chart-accountOverview').each()

}); // end document.ready()
</script>


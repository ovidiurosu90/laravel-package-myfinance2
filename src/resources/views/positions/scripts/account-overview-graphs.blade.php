@inject('ChartsBuilder', 'ovidiuro\myfinance2\App\Services\ChartsBuilder')

<script type="module">

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
        const accountOverviewChart = LightweightCharts.createChart(
            chartElement,
            {
                width: chartElement.clientWidth - 30,
                height: 250,
                layout: {
                    attributionLogo: false,
                },
                localization: {
                    priceFormatter: Intl.NumberFormat('en-US', {
                        style: 'currency',
                        currency: $(this).data('account_currency_iso_code'),
                    }).format,
                },
            } // end chartOptions
        ); // end createChart

        @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
        const series_{{ $metric }} = accountOverviewChart.addSeries(
            LightweightCharts.BaselineSeries,
            {
                lineColor: '{{ $properties['line_color'] }}',
                topLineColor: '{{ $properties['line_color'] }}',
                bottomLineColor: '{{ $properties['line_color'] }}',
                title: '{{ $properties['title'] }}',
            } // end seriesProperties
        ); // end addSeries

        series_{{ $metric }}.setData(metricData[accountId]['{{ $metric }}']);

        @endforeach

    }); // end $('.chart-accountOverview').each()

}); // end document.ready()
</script>


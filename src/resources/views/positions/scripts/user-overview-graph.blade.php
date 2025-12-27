@inject('ChartsBuilder', 'ovidiuro\myfinance2\App\Services\ChartsBuilder')

<script type="module">

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
    const userOverviewChart = LightweightCharts.createChart(
        chartElement,
        {
            width: chartElement.clientWidth, // - 14,
            height: 250,
            layout: {
                attributionLogo: false,
            },
        } // end chartOptions
    ); // end createChart

    function setLocalization()
    {
        userOverviewChart.applyOptions({
            localization: {
                priceFormatter: Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency: $element.data('currency_iso_code'),
                }).format,
            },
        });
    }

    function setStatus()
    {
        const currency = $element.data('currency_iso_code');

        // Preparing absolute values to be used for calculating percentages
        const pMValue = userOverviewData['mvalue_' + currency];
        const pCost = userOverviewData['cost_' + currency];
        const pChange = userOverviewData['change_' + currency];
        const pCash = userOverviewData['cash_' + currency];

        @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
        const {{ $metric }}Stats = userOverviewData['{{ $metric }}_' + currency];
        const {{ $metric }}Last = {{ $metric }}Stats[{{ $metric }}Stats.length -1];

        const {{ $metric }}Value = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: $element.data('currency_iso_code'),
        }).format({{ $metric }}Last.value);

        const ${{ $metric }}Element = $('#{{ $metric }}-status');
        ${{ $metric }}Element.css('color', '{{ $properties["line_color"] }}');
        ${{ $metric }}Element.html({{ $metric }}Value + " " + '{{ $metric }}');

        // Populating percentages
        const ${{ $metric }}ElementPercentage = $('#{{ $metric }}-status-percentage');
        @switch($metric)
            @case('cost')
                ${{ $metric }}ElementPercentage.html('-100%');
                @break
            @case('mvalue')
                ${{ $metric }}ElementPercentage.html('+' +
                    Math.round(100 * pMValue[pMValue.length - 1].value
                               / pCost[pCost.length - 1].value * 100) / 100
                    + '%');
                @break
            @case('change')
                ${{ $metric }}ElementPercentage.html('&nbsp;&nbsp;&nbsp;' +
                    Math.round(100 * pChange[pChange.length - 1].value
                               / pCost[pCost.length - 1].value * 100) / 100
                    + '%');
                @break
            @case('cash')
                ${{ $metric }}ElementPercentage.html(
                    Math.round(100 * pCash[pCash.length - 1].value
                               / pCost[pCost.length - 1].value * 100) / 100
                    + '%');
                @break
        @endswitch
            ${{ $metric }}ElementPercentage.css('color',
                '{{ $properties["line_color"] }}');
        @endforeach
    }

    setLocalization();
    setStatus();

    const currencyExchangeLast =
        currencyExchangeData[currencyExchangeData.length - 1];
    var $currencyExchangeElement = $('#currency_exchange-status');
    $currencyExchangeElement.html("EURUSD " + currencyExchangeLast.value);
    $currencyExchangeElement.attr('title', currencyExchangeLast.time);
    $('#currency_exchange-status-time').html(currencyExchangeLast.time);

    @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
    const series_{{ $metric }} = userOverviewChart.addSeries(
        LightweightCharts.BaselineSeries,
        {
            lineColor: '{{ $properties['line_color'] }}',
            topLineColor: '{{ $properties['line_color'] }}',
            bottomLineColor: '{{ $properties['line_color'] }}',
            title: '{{ $properties['title'] }}',
        } // end seriesProperties
    ); // end addSeries

    series_{{ $metric }}.setData(userOverviewData['{{ $metric }}_'
        + $element.data('currency_iso_code')]);

    @endforeach

    var $toggleCurrencySelect = $("#toggle-currency-select");
    $toggleCurrencySelect.change(function()
    {
        const url = new URL(window.location.href);

        if ($(this).is(':checked')) {
            $element.data('currency_iso_code', 'EUR');
            url.searchParams.set('currency_iso_code', 'EUR');
        } else {
            $element.data('currency_iso_code', 'USD');
            url.searchParams.set('currency_iso_code', 'USD');
        }
        window.history.replaceState(null, null, url); // or pushState

        setLocalization();
        setStatus();

        @foreach($ChartsBuilder::getAccountMetrics() as $metric => $properties)
        series_{{ $metric }}.setData(userOverviewData['{{ $metric }}_'
            + $element.data('currency_iso_code')]);
        @endforeach
    });

}); // end document.ready()
</script>


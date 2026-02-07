{{-- Returns Overview Graph Script
     Creates a chart showing returns data across all years.
     - Left Y-axis: Total returns aggregated across all accounts
     - Right Y-axis: Individual account returns
     - Currency toggle updates all series data
--}}

<script type="module">

// Color palette for account series (avoiding red/green which are used for total bars)
const accountColors = [
    'rgba(67, 83, 254, 1)',    // Blue
    'rgba(255, 152, 0, 1)',    // Orange
    'rgba(156, 39, 176, 1)',   // Purple
    'rgba(0, 188, 212, 1)',    // Cyan
    'rgba(233, 30, 99, 1)',    // Pink
    'rgba(121, 85, 72, 1)',    // Brown
    'rgba(96, 125, 139, 1)',   // Blue Grey
    'rgba(255, 193, 7, 1)',    // Amber
    'rgba(63, 81, 181, 1)',    // Indigo
    'rgba(255, 87, 34, 1)',    // Deep Orange
];

// Total returns series color (distinct from account colors)
const totalColor = 'rgba(38, 166, 154, 1)'; // Teal

// Overview data passed from PHP
const overviewData = {!! json_encode($overviewData) !!};

// Custom primitive classes to draw labels above histogram bars
class BarLabelsRenderer
{
    constructor(data, options)
    {
        this._data = data;
        this._options = options;
    }

    draw(target)
    {
        target.useBitmapCoordinateSpace(scope => {
            const ctx = scope.context;
            const data = this._data;
            if (!data || !data.bars || data.bars.length === 0) return;

            ctx.font = `bold ${Math.round(14 * scope.verticalPixelRatio)}px Arial`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';

            data.bars.forEach(bar => {
                if (bar.x === null) return;

                const x = bar.x * scope.horizontalPixelRatio;
                const y = bar.y * scope.verticalPixelRatio;

                // Format value with currency
                const value = bar.originalValue;
                const formatted = new Intl.NumberFormat('en-US', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                }).format(Math.abs(value));
                const sign = value >= 0 ? '+' : '-';
                const currency = bar.currency || '€';
                const text = sign + formatted + ' ' + currency;

                // Draw text above the bar (or below if negative)
                const yOffset = value >= 0 ? -5 : 22;
                const textY = y + yOffset * scope.verticalPixelRatio;

                // White outline so text is readable over account lines
                ctx.strokeStyle = 'white';
                ctx.lineWidth = 4;
                ctx.lineJoin = 'round';
                ctx.strokeText(text, x, textY);

                // Colored text on top
                ctx.fillStyle = value >= 0 ? 'green' : 'red';
                ctx.fillText(text, x, textY);
            });
        });
    }
}

class BarLabelsPaneView
{
    constructor(source)
    {
        this._source = source;
        this._data = { bars: [] };
    }

    update()
    {
        const series = this._source._series;
        const timeScale = this._source._chart.timeScale();
        const data = this._source._data;
        const currency = this._source._currency || '€';

        this._data.bars = [];

        if (!data) return;

        data.forEach(item => {
            const x = timeScale.timeToCoordinate(item.time);
            const y = series.priceToCoordinate(item.value);

            if (x !== null && y !== null) {
                this._data.bars.push({
                    x: x,
                    y: y,
                    originalValue: item.value,
                    currency: currency,
                });
            }
        });
    }

    renderer()
    {
        return new BarLabelsRenderer(this._data, this._source._options);
    }
}

class BarLabelsPrimitive
{
    constructor(chart, series, data, currency)
    {
        this._chart = chart;
        this._series = series;
        this._data = data;
        this._currency = currency || '€';
        this._paneViews = [new BarLabelsPaneView(this)];
    }

    updateData(data, currency)
    {
        this._data = data;
        this._currency = currency || '€';
    }

    paneViews()
    {
        return this._paneViews;
    }

    updateAllViews()
    {
        this._paneViews.forEach(pv => pv.update());
    }

    attached(param)
    {
        this._requestUpdate = () => param.requestUpdate();
        this._requestUpdate();
    }

    requestUpdate()
    {
        if (this._requestUpdate) this._requestUpdate();
    }
}

$(document).ready(function()
{
    var $element = $('#chart-returns-overview');

    // Skip if no overview data or element not found
    if (!$element.length || !overviewData || !overviewData.total) {
        return;
    }

    const chartElement = $element[0];

    // Create chart with dual price scales
    const overviewChart = LightweightCharts.createChart(
        chartElement,
        {
            width: chartElement.clientWidth,
            height: 300,
            layout: {
                attributionLogo: false,
            },
            leftPriceScale: {
                visible: true,
                borderVisible: true,
            },
            rightPriceScale: {
                visible: true,
                borderVisible: true,
            },
            timeScale: {
                barSpacing: 50,
                minBarSpacing: 30,
                rightOffset: 0.35,
            },
            crosshair: {
                mode: LightweightCharts.CrosshairMode.Normal,
                vertLine: {
                    labelVisible: true,
                },
                horzLine: {
                    labelVisible: true,
                },
            },
        }
    );

    // Currency formatter - matches the format used in the returns table below
    function formatCurrency(value, currency)
    {
        const formatted = new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);

        const symbol = currency === 'EUR' ? '€' : '$';
        return formatted + ' ' + symbol;
    }

    // Currency formatter with sign for cumulative total display
    function formatCurrencyWithSign(value, currency)
    {
        const formatted = new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(Math.abs(value));

        const symbol = currency === 'EUR' ? '€' : '$';
        const sign = value >= 0 ? '+' : '-';
        return sign + formatted + ' ' + symbol;
    }

    // Store account series for later updates
    const accountSeries = {};
    let colorIndex = 0;

    // Create series for each account first (right axis) so they render behind the total bars
    if (overviewData.accounts) {
        Object.keys(overviewData.accounts).forEach(function(accountId)
        {
            const accountData = overviewData.accounts[accountId];
            const color = accountColors[colorIndex % accountColors.length];
            colorIndex++;

            accountSeries[accountId] = overviewChart.addSeries(
                LightweightCharts.LineSeries,
                {
                    color: color,
                    lineWidth: 2,
                    title: accountData.name,
                    priceScaleId: 'right',
                    priceFormat: {
                        type: 'custom',
                        minMove: 1,
                        formatter: (price) => formatCurrency(price,
                            $element.data('overview_currency')),
                    },
                }
            );

            // Store color for legend
            accountSeries[accountId]._color = color;
            accountSeries[accountId]._name = accountData.name;
        });
    }

    // Create the total returns series as histogram/bars (left axis) - added last so labels render on top
    const totalSeries = overviewChart.addSeries(
        LightweightCharts.HistogramSeries,
        {
            color: totalColor,
            priceScaleId: 'left',
            lastValueVisible: false,
            priceLineVisible: false,
            priceFormat: {
                type: 'custom',
                minMove: 1,
                formatter: (price) => formatCurrency(price, $element.data('overview_currency')),
            },
        }
    );

    // Create and attach labels primitive
    const initialCurrencyForPrimitive = $element.data('overview_currency') || 'EUR';
    const currencySymbol = initialCurrencyForPrimitive === 'EUR' ? '€' : '$';
    const barLabelsPrimitive = new BarLabelsPrimitive(
        overviewChart,
        totalSeries,
        overviewData.total[initialCurrencyForPrimitive] || [],
        currencySymbol
    );
    totalSeries.attachPrimitive(barLabelsPrimitive);

    // Function to format total data with colors for histogram
    function formatTotalDataForHistogram(currency)
    {
        const totalData = overviewData.total[currency];
        if (!totalData || totalData.length === 0) {
            return [];
        }

        return totalData.map(function(item)
        {
            return {
                time: item.time,
                value: item.value,
                color: item.value >= 0 ? 'rgba(38, 166, 154, 0.7)' : 'rgba(239, 83, 80, 0.7)',
            };
        });
    }

    // Function to update all series with current currency data
    function updateSeriesData(currency)
    {
        // Update total series (histogram with colored bars)
        if (overviewData.total && overviewData.total[currency]) {
            totalSeries.setData(formatTotalDataForHistogram(currency));

            // Update labels primitive with currency symbol
            const currencySymbol = currency === 'EUR' ? '€' : '$';
            barLabelsPrimitive.updateData(overviewData.total[currency], currencySymbol);
            barLabelsPrimitive.requestUpdate();
        }

        // Update account series
        Object.keys(accountSeries).forEach(function(accountId)
        {
            const accountData = overviewData.accounts[accountId];
            if (accountData && accountData[currency]) {
                accountSeries[accountId].setData(accountData[currency]);
            }
        });
    }

    // Function to update price formatters for new currency
    function updateFormatters(currency)
    {
        // Update histogram series formatter
        totalSeries.applyOptions({
            priceFormat: {
                type: 'custom',
                minMove: 1,
                formatter: (price) => formatCurrency(price, currency),
            },
        });

        // Update line series formatters
        Object.keys(accountSeries).forEach(function(accountId)
        {
            accountSeries[accountId].applyOptions({
                priceFormat: {
                    type: 'custom',
                    minMove: 1,
                    formatter: (price) => formatCurrency(price, currency),
                },
            });
        });
    }

    // Function to update the cumulative total in the header
    function updateCumulativeTotal(currency)
    {
        if (!overviewData.cumulativeTotal) {
            return;
        }

        const cumulativeTotal = overviewData.cumulativeTotal[currency] || 0;
        const formattedTotal = formatCurrencyWithSign(cumulativeTotal, currency);

        // Apply color based on value (green for positive, red for negative)
        const color = cumulativeTotal >= 0 ? 'green' : 'red';
        $('#overview-cumulative-total').html(
            '<span style="color: ' + color + ';">' + formattedTotal + '</span>'
        );
    }

    // Function to update status display
    function updateStatus(currency)
    {
        // Update cumulative total in header
        updateCumulativeTotal(currency);
    }

    // Function to build legend
    function buildLegend()
    {
        let legendHtml = '<div style="display: flex; flex-wrap: wrap; gap: 1rem;">';

        // Account legend items (bars legend removed - they're self-explanatory with labels)
        Object.keys(accountSeries).forEach(function(accountId)
        {
            const series = accountSeries[accountId];
            legendHtml += '<span style="display: inline-flex; align-items: center;">';
            legendHtml += '<span style="width: 12px; height: 2px; background-color: '
                + series._color + '; display: inline-block; margin-right: 4px;"></span>';
            legendHtml += '<span>' + series._name + '</span>';
            legendHtml += '</span>';
        });

        legendHtml += '</div>';
        $('#overview-legend').html(legendHtml);
    }

    // Initial data load
    const initialCurrency = $element.data('overview_currency') || 'EUR';
    updateSeriesData(initialCurrency);
    updateStatus(initialCurrency);
    buildLegend();

    // Apply scale margins
    overviewChart.priceScale('left').applyOptions({
        scaleMargins: {
            top: 0.1,
            bottom: 0.1,
        },
    });

    overviewChart.priceScale('right').applyOptions({
        scaleMargins: {
            top: 0.1,
            bottom: 0.1,
        },
    });

    // Fit content to show all data
    overviewChart.timeScale().fitContent();

    // Handle currency toggle (independent of main currency toggle)
    $('#toggle-overview-currency').change(function()
    {
        const newCurrency = $(this).is(':checked') ? 'EUR' : 'USD';

        // Update data attribute
        $element.data('overview_currency', newCurrency);

        // Update URL parameter (separate from main currency)
        const url = new URL(window.location.href);
        url.searchParams.set('overview_currency', newCurrency);
        window.history.replaceState(null, null, url);

        // Update formatters, data, and status
        updateFormatters(newCurrency);
        updateSeriesData(newCurrency);
        updateStatus(newCurrency);
    });

    // Handle collapse toggle for chevron icon
    $('#returns-overview-body').on('show.bs.collapse', function()
    {
        $('#returns-overview-chevron').removeClass('fa-chevron-right').addClass('fa-chevron-down');
    });

    $('#returns-overview-body').on('hide.bs.collapse', function()
    {
        $('#returns-overview-chevron').removeClass('fa-chevron-down').addClass('fa-chevron-right');
    });

    // Handle window resize
    $(window).on('resize', function()
    {
        overviewChart.applyOptions({
            width: chartElement.clientWidth,
        });
    });

}); // end document.ready()
</script>


<script type="module">
$(document).ready(function()
{
    const modalEl = document.getElementById('symbol-chart-modal');
    if (!modalEl) {
        return;
    }
    const chartContainer = document.getElementById('symbol-chart-modal-chart');
    const emptyEl        = document.getElementById('symbol-chart-modal-empty');

    // Hide the expand icon for symbols that have no stored chart series
    // (e.g. BTC-USD), since there is nothing to enlarge.
    $('.symbol-chart-expand').each(function()
    {
        const series = (window.__symbolChartSeries || {})[$(this).data('symbol')];
        if (!series || !series.length) {
            $(this).hide();
        }
    });

    let symbolChart = null;

    @include('myfinance2::general.scripts.partials.fmt-price')

    // Captured on show; the chart is built on shown.bs.modal so the container
    // has a measurable width.
    let currentSymbol    = null;
    let currentBaseValue = null;
    let currentAccountId = '';
    let currentCurrency  = '';

    function disposeChart()
    {
        if (symbolChart) {
            symbolChart.remove();
            symbolChart = null;
        }
        chartContainer.innerHTML = '';
    }

    function buildChart(symbol, baseValue)
    {
        disposeChart();

        const series = (window.__symbolChartSeries || {})[symbol];
        if (!series || !series.length) {
            chartContainer.style.display = 'none';
            emptyEl.style.display = 'block';
            return;
        }
        chartContainer.style.display = 'block';
        emptyEl.style.display = 'none';

        symbolChart = LightweightCharts.createChart(chartContainer, {
            width: chartContainer.clientWidth,
            height: 360,
            layout: { attributionLogo: false },
            localization: { priceFormatter: fmtPrice },
            grid: {
                vertLines: { visible: false },
                horzLines: { color: 'rgba(42, 46, 57, 0.2)' },
            },
            rightPriceScale: { borderVisible: false },
            timeScale: { borderVisible: false, timeVisible: false },
        });

        const seriesProperties = {
            lastValueVisible: true,
            priceFormat: { type: 'custom', minMove: 0.01, formatter: fmtPrice },
            topLineColor: 'rgba( 38, 166, 154, 1)',
            topFillColor1: 'rgba( 38, 166, 154, 0.28)',
            topFillColor2: 'rgba( 38, 166, 154, 0.05)',
            bottomLineColor: 'rgba( 239, 83, 80, 1)',
            bottomFillColor1: 'rgba( 239, 83, 80, 0.05)',
            bottomFillColor2: 'rgba( 239, 83, 80, 0.28)',
        };
        if (baseValue) {
            seriesProperties.baseValue = { type: 'price', price: baseValue };
        }

        const s = symbolChart.addSeries(LightweightCharts.BaselineSeries, seriesProperties);
        s.setData(series);
        symbolChart.timeScale().fitContent();
    }

    function resizeChart()
    {
        if (symbolChart) {
            symbolChart.applyOptions({ width: chartContainer.clientWidth });
        }
    }

    @include('myfinance2::general.scripts.partials.range-bar', ['rangeBarId' => 'scm-range-bar'])

    // Compact, two-column position metrics from the trigger's formatted
    // data-attrs: market figures on the left, gains (value + %) on the right.
    function buildPositionOverview($icon)
    {
        const mkRow = function(label, value)
        {
            if (value === undefined || value === null || value === '') {
                return '';
            }
            return '<tr><th class="fw-normal text-muted text-nowrap">' + label
                 + '</th><td class="text-end">' + value + '</td></tr>';
        };
        const mkGain = function(label, value, pct)
        {
            return mkRow(label, value
                ? value + (pct ? ' (' + pct + ')' : '') : '');
        };
        const mkTable = function(rows)
        {
            return rows
                ? '<table class="table table-sm mb-0"><tbody>' + rows + '</tbody></table>'
                : '';
        };

        const left = mkRow('MValue', $icon.data('mvalue'))
            + mkRow('Cost Basis', $icon.data('cost-basis'))
            + mkRow('Avg Cost', $icon.data('avg-cost'));
        const right = mkGain('Day Gain',
                $icon.data('day-gain'), $icon.data('day-gain-pct'))
            + mkGain('Overall Gain',
                $icon.data('overall-gain'), $icon.data('overall-gain-pct'));

        if (!left && !right) {
            $('#scm-overall').html('').hide();
            return;
        }

        $('#scm-overall').html(
            '<div class="row g-3">'
          +   '<div class="col-md-6">' + mkTable(left) + '</div>'
          +   '<div class="col-md-6">' + mkTable(right) + '</div>'
          + '</div>'
        ).show();
    }

    function loadFinanceData(symbol, accountId, currency)
    {
        $('#scm-range-bar').html('');
        $.ajax({
            type: 'GET',
            url: "{{ url('/get-finance-data') }}",
            data: { symbol: symbol, account_id: accountId },
            success: function(data)
            {
                buildRangeBar(data, currency || data.currency || '');
            },
        });
    }

    // Populate when the modal opens (Bootstrap data-API passes the trigger as
    // relatedTarget).
    modalEl.addEventListener('show.bs.modal', function(event)
    {
        const icon = event.relatedTarget;
        if (!icon) {
            return;
        }
        const $icon = $(icon);

        currentSymbol    = $icon.data('symbol');
        currentBaseValue = parseFloat($icon.data('base-value')) || null;
        currentAccountId = $icon.data('account-id') || '';
        currentCurrency  = $icon.data('currency') || '';

        const symbolName = $icon.data('symbol-name') || currentSymbol;

        const $link = $('#symbol-chart-modal-symbol-link');
        $link.text(currentSymbol)
             .attr('href', 'https://finance.yahoo.com/quote/' + currentSymbol);
        $('#symbol-chart-modal-name').html(symbolName);

        // Pre/post/at-close header (server-rendered, formatted with green/red)
        $('#scm-quote-details').html($icon.data('quote-header') || '');

        // Position overview: on the watchlist, reuse the position cards already
        // rendered in the row; on positions, build a compact metrics table from
        // the row's (already-formatted) values. Hidden when neither applies.
        const $opCards = $icon.closest('tr').find('.open-positions-cards').first();
        const $dialog  = $(modalEl).find('.modal-dialog');
        if ($opCards.length) {
            // Widen the dialog so the account cards sit side by side, wrapping
            // as space allows.
            $dialog.addClass('scm-wide');
            $('#scm-overall').html(
                '<div class="d-flex gap-2 flex-wrap align-items-start">'
                + $opCards.html() + '</div>'
            ).show();
        } else {
            $dialog.removeClass('scm-wide');
            buildPositionOverview($icon);
        }
    });

    modalEl.addEventListener('shown.bs.modal', function()
    {
        buildChart(currentSymbol, currentBaseValue);
        loadFinanceData(currentSymbol, currentAccountId, currentCurrency);
    });

    modalEl.addEventListener('hidden.bs.modal', function()
    {
        disposeChart();
    });

    window.addEventListener('resize', resizeChart);
});
</script>

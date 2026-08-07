{{--
    "Populate historical data" button behaviour for the symbol chart modal.

    Refetches the open symbol's daily closing prices (overwriting the stored days,
    which is the point after a stock split wiped the pre-split history) and redraws
    the chart from the refilled series.

    Included inside the modal's <script type="module">, so it reuses that scope's
    currentSymbol / currentBaseValue / currentAccountId and its buildChart() and
    loadTrades() helpers, and expects the modal's #scm-populate-historical button
    and #scm-populate-status line.

    Usage:
        @include('myfinance2::general.scripts.partials.populate-historical')
--}}

// The button's tooltip is left to the app-wide initialiser
// (general.scripts.tooltips), which also honours its skip-on-touch-devices rule.
const populateBtn    = document.getElementById('scm-populate-historical');
const populateStatus = document.getElementById('scm-populate-status');

function setPopulateStatus(message, cssClass)
{
    if (!populateStatus) {
        return;
    }
    populateStatus.className = 'small mb-2 ' + (cssClass || 'text-muted');
    populateStatus.textContent = message || '';
    populateStatus.style.display = message ? 'block' : 'none';
}

function setPopulateBusy(isBusy)
{
    if (!populateBtn) {
        return;
    }
    populateBtn.disabled = isBusy;
    populateBtn.innerHTML = isBusy
        ? '<span class="spinner-border spinner-border-sm me-1" role="status"'
          + ' aria-hidden="true"></span>Populating...'
        : '<i class="fas fa-clock-rotate-left me-1"></i>Populate historical data';
}

// Pull the rebuilt series (plus a fresh quote header) through the shared
// endpoint, so the modal shows exactly what every other chart surface will.
// doneMessage is the backfill's own success line, kept so this call can turn it
// into a warning when the refresh itself fails.
function reloadSymbolSeries(symbol, doneMessage)
{
    $.ajax({
        type: 'GET',
        url: "{{ url('/get-symbol-chart') }}",
        data: { symbol: symbol },
        success: function(data)
        {
            window.__symbolChartSeries = window.__symbolChartSeries || {};
            window.__symbolChartSeries[symbol] = data.series || [];

            // Ignore a late response if the modal moved to another symbol.
            if (currentSymbol !== symbol) {
                return;
            }
            if (data.quote_header) {
                $('#scm-quote-details').html(data.quote_header);
            }
            buildChart(symbol, currentBaseValue);
            loadTrades(symbol, currentAccountId);
            // This payload also carries the 52W range figures, so redraw the bar
            // from it rather than leaving the one /get-finance-data drew on open.
            buildRangeBar(data, currentCurrency || data.currency || '');
        },
        error: function()
        {
            // The backfill itself succeeded; only the redraw failed, so say so
            // instead of leaving a green line above a pre-backfill chart.
            if (currentSymbol !== symbol) {
                return;
            }
            setPopulateStatus(doneMessage
                + ' The chart could not be refreshed, reload the page to see it.',
                'text-warning');
        },
    });
}

$(populateBtn).on('click', function()
{
    const symbol = currentSymbol;
    if (!symbol) {
        return;
    }

    setPopulateBusy(true);
    setPopulateStatus('Fetching historical data for ' + symbol
        + ', this can take a few seconds...', 'text-muted');

    $.ajax({
        type: 'POST',
        url: "{{ url('/populate-historical-data') }}",
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        data: { symbol: symbol },
        success: function(data)
        {
            setPopulateBusy(false);
            const doneMessage = (data.message || 'Historical data populated.')
                + ' Reload the page to update the inline row chart.';
            setPopulateStatus(doneMessage, 'text-success');
            reloadSymbolSeries(symbol, doneMessage);
        },
        error: function(xhr)
        {
            setPopulateBusy(false);
            const response = xhr.responseJSON || {};
            setPopulateStatus(response.message
                || 'Could not populate the historical data.', 'text-danger');
        },
    });
});

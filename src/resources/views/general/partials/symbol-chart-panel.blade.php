{{--
    Symbol chart panel for the forms that take a symbol (orders, trades and price alerts,
    create and edit): the same quote header, 52-week range bar, price chart and buy/sell
    markers as the /positions and /watchlist-symbols "expand chart" modal.

    Hidden until general.scripts.symbol-chart-panel fills it for the form's #symbol-input,
    so include that script in the page's footer_scripts.

    Usage:
        @include('myfinance2::general.partials.symbol-chart-panel')
--}}
<div class="row" id="scp-panel-row" style="display: none;">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-2">
                    <a id="scp-symbol-link" href="#" target="_blank"
                       class="text-decoration-none fw-bold"></a>
                    <span id="scp-name" class="text-secondary ms-2"></span>
                </h6>
                <div class="d-flex flex-wrap justify-content-between
                            align-items-start gap-3 mb-3">
                    <div id="scp-quote-details" class="flex-grow-1"></div>
                    <div id="scp-range-bar" style="min-width: 220px;"></div>
                </div>
                <div id="scp-stale-warning" class="alert alert-warning py-2 px-3 mb-3 small"
                     style="display: none;">
                    The cached chart was out of date. Showing the latest history
                    rebuilt from stored prices; the cache has been refreshed.
                </div>
                <div id="scp-gap-warning" class="alert alert-warning py-2 px-3 mb-3 small"
                     style="display: none;"></div>
                <div id="scp-chart"
                     style="position: relative; width: 100%; height: 300px;"></div>
                <div id="scp-chart-empty" class="text-center text-muted py-4"
                     style="display: none;">
                    No chart data available for this symbol.
                </div>
            </div>
        </div>
    </div>
</div>

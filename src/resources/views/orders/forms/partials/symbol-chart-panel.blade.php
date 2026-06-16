<div class="row" id="osc-panel-row" style="display: none;">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-2">
                    <a id="osc-symbol-link" href="#" target="_blank"
                       class="text-decoration-none fw-bold"></a>
                    <span id="osc-name" class="text-secondary ms-2"></span>
                </h6>
                <div class="d-flex flex-wrap justify-content-between
                            align-items-start gap-3 mb-3">
                    <div id="osc-quote-details" class="flex-grow-1"></div>
                    <div id="osc-range-bar" style="min-width: 220px;"></div>
                </div>
                <div id="osc-stale-warning" class="alert alert-warning py-2 px-3 mb-3 small"
                     style="display: none;">
                    The cached chart was out of date. Showing the latest history
                    rebuilt from stored prices; the cache has been refreshed.
                </div>
                <div id="osc-gap-warning" class="alert alert-warning py-2 px-3 mb-3 small"
                     style="display: none;"></div>
                <div id="osc-chart"
                     style="position: relative; width: 100%; height: 300px;"></div>
                <div id="osc-chart-empty" class="text-center text-muted py-4"
                     style="display: none;">
                    No chart data available for this symbol.
                </div>
            </div>
        </div>
    </div>
</div>

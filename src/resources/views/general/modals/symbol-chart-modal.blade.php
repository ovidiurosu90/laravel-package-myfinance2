<style>
    /* Wider dialog for the watchlist account-cards view (Bootstrap's modal-xl
       only widens at >=1200px, so use an explicit max-width). */
    .modal-dialog.scm-wide {
        max-width: min(1200px, 95vw);
    }
    /* Cloned open-position cards: shrink each card to its content (the inner
       Bootstrap tables are width:100%, which otherwise stretches the card to
       the full row) so multiple accounts sit side by side. */
    #scm-overall .open-positions .metrics,
    #scm-overall .open-positions .trades {
        width: auto;
    }
    #scm-overall .d-flex > .card {
        flex: 0 1 auto;
        width: fit-content;
        max-width: 100%;
    }
</style>
@inject('HistoricalBackfill', 'ovidiuro\myfinance2\App\Services\HistoricalBackfill')
@php
    // Tooltip for the header's backfill button. Spells out the overwrite, since
    // the button is meant to be used on symbols that already have (incomplete)
    // history, e.g. after a stock split removed the pre-split prices.
    $scm_populateTip = "Refetches this symbol's daily closing prices from "
        . $HistoricalBackfill::DEFAULT_START_DATE
        . ' to today and rebuilds its chart. Days already stored are overwritten'
        . ' with the prices the data provider returns now, so a history left'
        . ' incomplete by a stock split is replaced by the split-adjusted one.';
@endphp
<div class="modal fade" id="symbol-chart-modal" tabindex="-1"
     aria-labelledby="symbol-chart-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="symbol-chart-modal-label">
                    <a id="symbol-chart-modal-symbol-link" href="#" target="_blank"
                       class="text-decoration-none fw-bold"></a>
                    <span id="symbol-chart-modal-name" class="text-secondary ms-2"></span>
                </h5>
                <button type="button" id="scm-populate-historical"
                        class="btn btn-sm btn-outline-secondary ms-auto me-2"
                        data-bs-toggle="tooltip" data-bs-placement="bottom"
                        title="{{ $scm_populateTip }}">
                    <i class="fas fa-clock-rotate-left me-1"></i>Populate historical data
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="scm-populate-status" class="small mb-2"
                     style="display: none;"></div>
                <div class="d-flex flex-wrap justify-content-between
                            align-items-start gap-3 mb-3">
                    <div id="scm-quote-details" class="flex-grow-1"></div>
                    <div id="scm-range-bar" style="min-width: 220px;"></div>
                </div>
                <div id="symbol-chart-modal-chart"
                     style="position: relative; width: 100%; height: 360px;"></div>
                <div id="symbol-chart-modal-empty" class="text-center text-muted py-5"
                     style="display: none;">
                    No chart data available for this symbol.
                </div>
                <div id="scm-overall" class="mt-3" style="display: none;"></div>
            </div>
        </div>
    </div>
</div>

<style>
    /* The per-day tier ribbon on the Tier line: each child span is one run of days, its width set inline. */
    .tier-history-strip {
        display: inline-flex;
        height: 10px;
        border-radius: 2px;
        overflow: hidden;
        background-color: #e9ecef;
        vertical-align: middle;
    }
    .tier-history-strip > span {
        display: block;
        height: 100%;
        min-width: 1px;
    }
    /* The Tier line's "details" toggle: the chevron turns up while the approach table is open. */
    .tier-approaches-toggle .fa-chevron-down {
        transition: transform 0.15s;
    }
    .tier-approaches-toggle[aria-expanded="true"] .fa-chevron-down {
        transform: rotate(180deg);
    }
    .modal-dialog.thm-dialog {
        max-width: min(1500px, 96vw);
    }
    .thm-swatch {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 2px;
        vertical-align: middle;
    }
    /* Hover label of the tier chart: the two closes behind the day's 1Y market return. */
    .thm-tip {
        position: absolute;
        z-index: 3;
        pointer-events: none;
        white-space: nowrap;
        text-align: right;
        font-size: 0.75rem;
        line-height: 1.3;
        padding: 2px 6px;
        border-radius: 3px;
        border: 1px solid var(--bs-border-color, #dee2e6);
        background-color: var(--bs-body-bg, #fff);
        color: var(--bs-body-color, #212529);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
    }
    /* The (i) tooltips: as wide as their table, with the explanation wrapping above it. */
    .thm-tooltip {
        --bs-tooltip-max-width: none;
    }
    .thm-tooltip .tooltip-inner {
        max-width: none;
        width: max-content;
        text-align: left;
    }
    .thm-tooltip .thm-info-text {
        max-width: 520px;
        white-space: normal;
        margin-bottom: 0.25rem;
    }
    /* Lighter green / red, readable on the dark tooltip background. */
    .thm-tooltip .text-success {
        color: #75b798 !important;
    }
    .thm-tooltip .text-danger {
        color: #ea868f !important;
    }
    .thm-info-table th,
    .thm-info-table td {
        padding: 0 6px;
        text-align: right;
        white-space: nowrap;
    }
    .thm-info-table th:first-child,
    .thm-info-table td:first-child {
        text-align: left;
    }
    /* Line-style sample in the hover label, matching the chart line it describes. */
    .thm-line-sample {
        display: inline-block;
        width: 16px;
        margin-right: 4px;
        vertical-align: middle;
        border-top: 2px solid #0d6efd;
    }
    .thm-line-sample.thm-line-dashed {
        border-top-style: dashed;
    }
</style>
<div class="modal fade" id="tier-history-modal" tabindex="-1"
     aria-labelledby="tier-history-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered thm-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tier-history-modal-label">
                    Tier history: <span id="thm-symbol" class="fw-bold"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="thm-loading" class="text-center text-muted py-5">Loading tier history...</div>
                <div id="thm-empty" class="text-center text-muted py-5" style="display:none;">
                    No tier history stored for this symbol yet. Run finance:tier-history-backfill.
                </div>
                {{-- The (i) tooltips (explanation and a table) are filled per symbol by the script. --}}
                <div id="thm-note" class="d-flex flex-wrap align-items-baseline gap-2 mb-1" style="display:none;">
                    <span class="fw-semibold">A. Tier history, based on the 1Y market return</span>
                    <span class="small text-muted">the stock's 12-month return on each trading day; B / S mark
                        your trades</span>
                    <span id="thm-tier-info" class="text-muted">
                        <i class="fa fa-info-circle" aria-hidden="true"></i>
                    </span>
                </div>
                <div id="thm-chart" style="position:relative;width:100%;height:262px;display:none;"></div>
                <div id="thm-position" class="mt-3" style="display:none;">
                    <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
                        <span class="fw-semibold">B. Your holdings</span>
                        <span class="small text-muted">your return per holding period (solid) vs the same trades
                            in VUSA (dashed)</span>
                        <span id="thm-pos-info" class="text-muted">
                            <i class="fa fa-info-circle" aria-hidden="true"></i>
                        </span>
                    </div>
                    <div id="thm-pos-chart" style="position:relative;width:100%;height:262px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

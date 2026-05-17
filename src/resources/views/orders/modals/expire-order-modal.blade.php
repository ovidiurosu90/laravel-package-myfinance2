<div class="modal fade" id="expire-order-modal" tabindex="-1" role="dialog"
    aria-labelledby="expire-order-modal-title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h4 class="modal-title" id="expire-order-modal-title">
                    Expire Order <strong id="expire-order-id-display"></strong>
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to mark this order as <strong>EXPIRED</strong>?</p>

                <hr>

                <p class="fw-semibold mb-1">Create a price alert?</p>
                <p id="expire-alert-suggestion" class="text-muted small mb-3"></p>

                <div class="d-flex flex-wrap gap-2">
                    <form id="expire-only-form" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning btn-sm">
                            Expire only
                        </button>
                    </form>
                    <form id="expire-with-alert-form" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="create_alert" value="1">
                        <input type="hidden" name="alert_type" id="expire-alert-type-input">
                        <input type="hidden" name="target_price" id="expire-target-price-input">
                        <input type="hidden" name="trade_currency_id" id="expire-trade-currency-input">
                        <button type="submit" class="btn btn-warning btn-sm">
                            Expire &amp; Create Alert
                        </button>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary"
                    data-bs-dismiss="modal">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

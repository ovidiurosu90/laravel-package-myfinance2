<div class="modal fade" id="fill-order-modal" tabindex="-1" role="dialog"
    aria-labelledby="fill-order-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="fill-order-modal-title">
                    Fill Order <strong id="fill-order-id-display"></strong>
                    <span id="fill-order-label-display" class="fw-normal fs-6 text-muted ms-1"></span>
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    Mark this order as <strong>FILLED</strong>.
                    Optionally link it to an existing trade or create a new one.
                </p>

                <h6>Option A: Link to existing trade</h6>
                <form id="fill-link-form" method="POST" class="mb-3">
                    @csrf
                    <div class="input-group">
                        <select name="trade_id" class="form-select">
                            <option value="">— select a trade —</option>
                            @foreach ($trades as $trade)
                                <option value="{{ $trade->id }}">
                                    #{{ $trade->id }} {{ $trade->getShortLabel() }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-success">
                            Fill &amp; Link Trade
                        </button>
                    </div>
                </form>

                <hr>

                <h6>Option B: Create a new trade</h6>
                <p class="text-muted small">
                    Marks order as filled, then opens the trade create form pre-filled
                    with order data.
                </p>
                <form id="fill-create-form" method="POST" class="mb-3">
                    @csrf
                    <input type="hidden" name="create_trade" value="1">
                    <button type="submit" class="btn btn-outline-primary">
                        Fill &amp; Create New Trade
                    </button>
                </form>

                <hr>

                <h6>Option C: Just mark as filled</h6>
                <form id="fill-only-form" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">
                        Fill (no trade link)
                    </button>
                </form>
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

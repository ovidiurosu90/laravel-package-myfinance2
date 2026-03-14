<div class="modal fade" id="link-trade-modal" tabindex="-1" role="dialog"
    aria-labelledby="link-trade-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="link-trade-modal-title">
                    Link Trade to Order <strong id="link-trade-order-id-display"></strong>
                    <span id="link-trade-order-label-display" class="fw-normal fs-6 text-muted ms-1"></span>
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="link-trade-form" method="POST">
                    @csrf
                    <div class="input-group">
                        <select name="trade_id" id="link-trade-id-input" class="form-select" required>
                            <option value="">— select a trade —</option>
                            @foreach ($trades as $trade)
                                <option value="{{ $trade->id }}">
                                    #{{ $trade->id }} {{ $trade->getShortLabel() }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary">
                            Link Trade
                        </button>
                    </div>
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

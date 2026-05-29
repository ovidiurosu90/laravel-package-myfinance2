<div class="modal fade" id="tier-override-modal" tabindex="-1" aria-labelledby="tier-override-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="tier-override-modal-title">
                    Override tier for <span id="tier-override-symbol"></span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-2">
                <div class="mb-2">
                    <label for="tier-override-select" class="form-label form-label-sm small mb-1">
                        Tier
                    </label>
                    <select id="tier-override-select"
                        name="tier_override"
                        form="tier-override-save-form"
                        class="form-select form-select-sm">
                        <option value="PLATINUM">Platinum (&gt; 15%)</option>
                        <option value="GOLD">Gold (10-15%)</option>
                        <option value="SILVER">Silver (5-10%)</option>
                        <option value="BRONZE">Bronze (0-5%)</option>
                        <option value="RUST">Rust (&lt; 0%)</option>
                    </select>
                    <div class="form-text small">
                        Computed: <span id="tier-override-computed" class="text-muted"></span>
                    </div>
                </div>
                <div class="mb-1">
                    <label for="tier-override-note" class="form-label form-label-sm small mb-1">
                        Note (optional)
                    </label>
                    <input type="text" id="tier-override-note"
                        name="note"
                        form="tier-override-save-form"
                        class="form-control form-control-sm"
                        placeholder="Why this override?" maxlength="500">
                </div>
            </div>
            <div class="modal-footer py-2 d-flex justify-content-between">
                <form id="tier-override-remove-form" method="POST" style="display:none">
                    @csrf
                    @method('DELETE')
                </form>
                <button type="submit" form="tier-override-remove-form"
                    class="btn btn-sm btn-outline-danger"
                    id="tier-override-remove" style="display:none">
                    Remove override
                </button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="tier-override-save-form"
                        class="btn btn-sm btn-primary">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Forms placed outside the modal to avoid any nesting issues --}}
<form id="tier-override-save-form" method="POST" style="display:none">
    @csrf
    <input type="hidden" name="symbol" id="tier-override-symbol-input">
</form>

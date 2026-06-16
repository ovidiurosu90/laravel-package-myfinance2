<script type="module">
// The most recent available quantity reported by the backend, cached so the
// max ceiling can be recomputed whenever the action changes without refetching.
window.lastAvailableQuantity = null;

// Apply (or clear) the quantity ceiling based on the CURRENT action. The
// "available quantity" cap only makes sense for a SELL (you cannot sell more
// than you hold); a BUY has no such upper bound, so any stale max is removed.
window.applyQuantityMax = function()
{
    var action = $('#action-select').val();
    var $input = $('#quantity-input');
    var qty    = window.lastAvailableQuantity;

    if (action === 'SELL' && qty != null) {
        $input.attr('max', qty);
        var currentQty = parseFloat($input.val());
        if (!isNaN(currentQty) && currentQty > qty) {
            $input.val(qty).trigger('input');
        }
    } else {
        $input.removeAttr('max');
    }
};

window.handleAvailableQuantity = function(qty)
{
    var $el = $('#available-quantity');

    window.lastAvailableQuantity = qty;

    if (qty != null) {
        $el.find('span').text(qty);
        $el.show();
    } else {
        $el.find('span').text('');
        $el.hide();
    }

    window.applyQuantityMax();
};

// Re-evaluate the ceiling whenever the action toggles (Buy <-> Sell). Selectize
// fires a native change event on the underlying select, so a delegated handler
// catches it regardless of initialisation order.
$(document).on('change', '#action-select', function()
{
    window.applyQuantityMax();
});
</script>

<script type="module">
window.handleAvailableQuantity = function(qty)
{
    var $el    = $('#available-quantity');
    var action = $('#action-select').val();
    var $input = $('#quantity-input');
    if (qty != null) {
        $el.find('span').text(qty);
        $el.show();
        if (action === 'SELL') {
            $input.attr('max', qty);
            var currentQty = parseFloat($input.val());
            if (!isNaN(currentQty) && currentQty > qty) {
                $input.val(qty).trigger('input');
            }
        }
    } else {
        $el.find('span').text('');
        $el.hide();
        $input.removeAttr('max');
    }
};

$(document).ready(function()
{
    $('#account-select').on('change', function()
    {
        if ($('#symbol-input').val()) {
            $('#get-finance-data').trigger('click');
        }
    });
});
</script>

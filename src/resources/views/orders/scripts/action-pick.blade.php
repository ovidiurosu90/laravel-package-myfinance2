{{-- The side picker under the order form's Action select, shown while a symbol is set but no side: one click on Buy or
     Sell sets the action (orders/scripts/finance then fills the limit, quantity and account for it). The hint says
     what today's tier reading calls for (orders/scripts/tier-reading, 'order-side-hint') and whether anything of the
     symbol is held ('order-sides-loaded'); it never picks the side. --}}
<script type="module">
$(document).ready(function ()
{
    const $pick   = $('#action-pick');
    const $hint   = $('#action-pick-hint');
    const $select = $('#action-select');
    if (!$pick.length) {
        return;
    }
    let held = null;
    let hint = null;

    function hintText()
    {
        const parts = [];
        if (hint) {
            const side = hint.side === 'BUY' ? 'a buy' : (hint.side === 'SELL' ? 'a sell' : 'no side');
            parts.push('Tier reading: ' + hint.label + ': ' + hint.action + ', ' + side + '.');
        }
        if (held === false) {
            parts.push('Nothing held to sell.');
        }
        parts.push('The limit (1% from the price), quantity and account follow the side.');

        return parts.join(' ');
    }

    function render()
    {
        const symbol = ($('#symbol-input').val() || '').trim();
        if (!symbol || $select.val()) {
            $pick.hide();
            return;
        }
        $pick.find('[data-side]').each(function ()
        {
            const side    = $(this).data('side');
            const hinted  = hint !== null && hint.side === side;
            const outline = side === 'BUY' ? 'btn-outline-success' : 'btn-outline-danger';
            const solid   = side === 'BUY' ? 'btn-success' : 'btn-danger';
            $(this).toggleClass(solid, hinted).toggleClass(outline, !hinted);
        });
        $hint.text(hintText());
        $pick.show();
    }

    $pick.on('click', '[data-side]', function ()
    {
        $select[0].selectize.setValue($(this).data('side'));
    });
    $(document).on('order-sides-loaded', function (event, sides)
    {
        held = sides ? !!sides.held : null;
        render();
    });
    $(document).on('order-side-hint', function (event, sideHint)
    {
        hint = sideHint || null;
        render();
    });
    $select.on('change', render);
    $('#symbol-input').on('input', function ()
    {
        held = null;
        hint = null;
        render();
    });
});
</script>

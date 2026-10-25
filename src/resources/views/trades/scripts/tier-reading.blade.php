{{-- The trade form's tier reading (TradeTierReadingController): loaded when the symbol, action and timestamp are set,
     refreshed when one of them changes. $tierReadingUrl: the endpoint; $tierTradeId: the trade being edited, or null;
     $tierOrderId: the order the trade fills, or null. --}}
<script type="module">
$(document).ready(function ()
{
    const $wrapper = $('#tier-reading-wrapper');
    const fetchUrl = @json($tierReadingUrl);
    const tradeId  = @json($tierTradeId ?? null);
    const orderId  = @json($tierOrderId ?? null);
    let requestId  = 0;
    let timer      = null;

    function refresh()
    {
        const data = {
            symbol: ($('#symbol-input').val() || '').trim().toUpperCase(),
            action: $('#action-select').val() || '',
            timestamp: ($('input[name="timestamp"]').val() || '').trim(),
            quantity: $('#quantity-input').val() || '',
            unit_price: $('#unit_price').val() || '',
            trade_id: tradeId || '',
            order_id: orderId || '',
        };
        if (!data.symbol || !data.action || !data.timestamp) {
            $wrapper.html('');
            return;
        }
        const reqId = ++requestId;
        $.ajax({
            type: 'GET',
            url: fetchUrl,
            data: data,
            success: function (response)
            {
                if (reqId !== requestId) {
                    return; // a newer request is on its way
                }
                $wrapper.find('[data-bs-toggle="tooltip"]').tooltip('dispose');
                $wrapper.html(response.html);
                $wrapper.find('[data-bs-toggle="tooltip"]').tooltip();
            },
        });
    }

    function refreshSoon()
    {
        clearTimeout(timer);
        timer = setTimeout(refresh, 600);
    }

    $('#symbol-input').on('blur', refresh);
    $('#get-finance-data').on('click', refresh);
    $('#action-select').on('change', refresh);
    $('input[name="timestamp"]').on('change', refreshSoon);
    $('#timestamp-picker').on('change.td', refreshSoon);

    refresh();
});
</script>

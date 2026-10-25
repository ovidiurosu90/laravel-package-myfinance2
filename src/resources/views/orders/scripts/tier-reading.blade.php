{{-- The tier block of the order form (OrderTierReadingController): loaded when the symbol and action are set,
     refreshed when the quantity or limit price change; and the confirmation on save when a serious warning is still
     to confirm (tier-confirm-modal). $tierReadingUrl: the endpoint; $tierOrderId: the order being edited, or null. --}}
<script type="module">
$(document).ready(function ()
{
    const $wrapper = $('#tier-reading-wrapper');
    const $modal   = $('#tier-confirm-modal');
    const $form    = $wrapper.closest('.card').find('form').first();
    const fetchUrl = @json($tierReadingUrl);
    const orderId  = @json($tierOrderId ?? null);
    let missing    = $modal.data('missing') || [];
    let requestId  = 0;
    let timer      = null;
    let confirmed  = false;

    function values()
    {
        return {
            symbol: ($('#symbol-input').val() || '').trim().toUpperCase(),
            action: $('#action-select').val() || '',
            quantity: $('#quantity-input').val() || '',
            limit_price: $('#limit_price').val() || '',
            order_id: orderId || '',
        };
    }

    function refresh()
    {
        const data = values();
        if (!data.symbol || !data.action) {
            $wrapper.html('');
            missing = [];
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
                missing = response.missing || [];
                $('#tier-confirm-body').html(response.confirm_html || '');
            },
        });
    }

    function refreshSoon()
    {
        clearTimeout(timer);
        timer = setTimeout(refresh, 600);
    }

    function acknowledge(codes)
    {
        $form.find('input[name="acknowledged_warnings[]"]').remove();
        codes.forEach(function (code)
        {
            $('<input type="hidden" name="acknowledged_warnings[]">').val(code).appendTo($form);
        });
    }

    function openConfirmation()
    {
        // Bootstrap's data API (the modal is not a jQuery plugin in this build).
        document.getElementById('tier-confirm-open').click();
    }

    $form.on('submit', function (event)
    {
        if (confirmed || !missing.length) {
            return;
        }
        event.preventDefault();
        openConfirmation();
    });
    $('#tier-confirm-accept').on('click', function ()
    {
        acknowledge(missing);
        confirmed = true;
        $(this).prop('disabled', true);
        $form.trigger('submit');
    });
    $modal.on('shown.bs.modal', function ()
    {
        $('#tier-confirm-back').trigger('focus');
    });

    $('#symbol-input').on('blur', refresh);
    $('#get-finance-data').on('click', refresh);
    $('#action-select').on('change', refresh);
    $('#quantity-input, #limit_price').on('input', refreshSoon);

    if ($modal.data('open-on-load') === true || $modal.data('open-on-load') === 'true') {
        openConfirmation();
    }
    refresh();
});
</script>

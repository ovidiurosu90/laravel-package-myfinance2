<script type="module">
$(document).ready(function()
{
    // Fill order modal: set form action URLs dynamically based on order id
    document.getElementById('fill-order-modal').addEventListener('shown.bs.modal', (e) =>
    {
        var orderId = $(e.relatedTarget).data('order-id');
        var orderLabel = $(e.relatedTarget).data('order-label') || '';
        var baseUrl = '{{ url('/orders') }}/' + orderId + '/fill';
        $('#fill-order-id-display').text('#' + orderId);
        $('#fill-order-label-display').text(orderLabel ? '— ' + orderLabel : '');
        $('#fill-link-form').attr('action', baseUrl);
        $('#fill-create-form').attr('action', baseUrl);
        $('#fill-only-form').attr('action', baseUrl);
        $('#fill-link-form select[name="trade_id"]').val('').trigger('change');
    });

    // Expire order modal: set form actions and price alert suggestion based on order data
    document.getElementById('expire-order-modal').addEventListener('shown.bs.modal', (e) =>
    {
        var orderId = $(e.relatedTarget).data('order-id');
        var symbol = $(e.relatedTarget).data('order-symbol');
        var action = $(e.relatedTarget).data('order-action');
        var price = $(e.relatedTarget).data('order-price');
        var currencyId = $(e.relatedTarget).data('order-currency') || '';

        var baseUrl = '{{ url('/orders') }}/' + orderId + '/expire';
        var alertType = (action === 'SELL') ? 'PRICE_ABOVE' : 'PRICE_BELOW';
        var direction = (action === 'SELL')
            ? 'alert when ' + symbol + ' goes above ' + price + ' (PRICE_ABOVE)'
            : 'alert when ' + symbol + ' drops below ' + price + ' (PRICE_BELOW)';

        $('#expire-order-id-display').text('#' + orderId);
        $('#expire-alert-suggestion').text(
            'For a ' + action + ' order: ' + direction + '.'
        );
        $('#expire-only-form').attr('action', baseUrl);
        $('#expire-with-alert-form').attr('action', baseUrl);
        $('#expire-alert-type-input').val(alertType);
        $('#expire-target-price-input').val(price);
        $('#expire-trade-currency-input').val(currencyId);
    });

    // Link trade modal: set form action URL dynamically based on order id
    document.getElementById('link-trade-modal').addEventListener('shown.bs.modal', (e) =>
    {
        var orderId = $(e.relatedTarget).data('order-id');
        var orderLabel = $(e.relatedTarget).data('order-label') || '';
        var linkUrl = '{{ url('/orders') }}/' + orderId + '/link-trade';
        $('#link-trade-order-id-display').text('#' + orderId);
        $('#link-trade-order-label-display').text(orderLabel ? '— ' + orderLabel : '');
        $('#link-trade-form').attr('action', linkUrl);
        $('#link-trade-id-input').val('').trigger('change');
    });
});
</script>

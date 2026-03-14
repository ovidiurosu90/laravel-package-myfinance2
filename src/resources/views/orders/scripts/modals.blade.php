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

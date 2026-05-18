<script type="module">
$(document).ready(function ()
{
    var $wrapper = $('#open-alerts-banner-wrapper');
    var fetchUrl = @json($openAlertsFetchUrl);
    var debounceTimer = null;

    function refreshOnSymbolChange()
    {
        var symbol = $('#symbol-input').val().trim().toUpperCase();

        $('#order-summary-banner').trigger('banner-update');

        if (!symbol) {
            $wrapper.html('');
            return;
        }

        $.ajax({
            type: 'GET',
            url: fetchUrl,
            data: { symbol: symbol },
            success: function (html)
            {
                $wrapper.html(html);
            },
        });
    }

    $('#symbol-input').on('input', function ()
    {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refreshOnSymbolChange, 600);
    });
});
</script>

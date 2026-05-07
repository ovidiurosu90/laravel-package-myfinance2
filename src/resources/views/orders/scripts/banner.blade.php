<script type="module">
$(document).ready(function ()
{
    var $banner             = $('#order-summary-banner');
    var tradeCurrencies     = $banner.data('trade-currencies') || [];
    var tradeCurrenciesById = {};
    for (var i in tradeCurrencies) {
        tradeCurrenciesById[tradeCurrencies[i]['id']] = tradeCurrencies[i];
    }
    var avgCostRaw      = $banner.attr('data-avg-cost');
    var totalOpenQtyRaw = $banner.attr('data-total-open-qty');
    var avgCost         = avgCostRaw !== '' ? parseFloat(avgCostRaw) : NaN;
    var totalOpenQty    = totalOpenQtyRaw !== '' ? parseFloat(totalOpenQtyRaw) : NaN;

    function updateBanner()
    {
        var action   = $('#action-select').val();
        var qty      = parseFloat($('#quantity-input').val());
        var priceStr = $('#limit_price').val();
        var price    = parseFloat(priceStr);
        var symbol   = $('#symbol-input').val() || '';

        if (!action || isNaN(qty) || qty <= 0 || isNaN(price) || price <= 0) {
            $banner.hide();
            return;
        }

        var total       = qty * price;
        var exRate      = parseFloat($('#exchange_rate').val());
        var tcId        = $('#trade_currency-select').val();
        var tc          = tcId ? tradeCurrenciesById[tcId] : null;
        var currDisplay = tc ? tc['display_code'] : '';
        var acctDisplay = $('#account_currency-label-tooltip').text().trim();
        var weakSignal  = !!$banner.data('weak-signal');
        var reason      = $banner.data('reason') || '';
        var alertClass  = weakSignal ? 'alert-warning' : (action === 'BUY' ? 'alert-success' : 'alert-danger');
        var qtyDisplay  = parseFloat(qty.toFixed(8));

        var html = '<strong>' + action + '</strong> '
            + qtyDisplay + 'x ' + symbol
            + ' @ ' + priceStr + (currDisplay ? ' ' + currDisplay : '')
            + ' ≈ ' + total.toFixed(2) + (currDisplay ? ' ' + currDisplay : '');

        if (!isNaN(exRate) && exRate > 0 && exRate !== 1 && acctDisplay) {
            html += ' (~' + (total / exRate).toFixed(2) + ' ' + acctDisplay + ')';
        }

        if (action === 'SELL' && !isNaN(avgCost) && avgCost > 0) {
            var gainPerUnit = price - avgCost;
            var totalGain   = gainPerUnit * qty;
            var gainPct     = (gainPerUnit / avgCost) * 100;
            var isLoss      = totalGain < 0;
            var sign        = totalGain >= 0 ? '+' : '';
            html += ' — Projected ' + (isLoss ? 'Loss ⚠️' : 'Gain ✅') + ': '
                + sign + totalGain.toFixed(2) + ' (' + sign + gainPct.toFixed(2) + '%)'
                + '<span class="text-muted ms-2">@ avg ' + avgCost.toFixed(2)
                + (currDisplay ? ' ' + currDisplay : '') + '</span>';
            if (isLoss) {
                html += '<div class="mt-1 text-muted">'
                    + 'Selling at this price would still realize a loss vs your avg cost.</div>';
            }
            if (!isNaN(totalOpenQty) && totalOpenQty > 0 && qty < totalOpenQty) {
                var openQtyDisplay = parseFloat(totalOpenQty.toFixed(8));
                html += ' <span class="badge bg-warning text-dark ms-1">partial — '
                    + qtyDisplay + ' of ' + openQtyDisplay + '</span>';
            }
        }

        if (reason) {
            html += ' — <span class="badge bg-secondary me-1">reason</span><em>' + reason + '</em>';
        }

        $banner
            .removeClass('alert-success alert-danger alert-warning')
            .addClass(alertClass)
            .html(html)
            .show();
    }

    $banner.on('banner-update', updateBanner);
    $('#quantity-input, #limit_price, #exchange_rate').on('input', updateBanner);
    $('#action-select, #trade_currency-select, #account-select').on('change', updateBanner);
    updateBanner();
});
</script>

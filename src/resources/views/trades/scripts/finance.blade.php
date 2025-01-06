<script type="module">
$(document).ready(function()
{
    var tradeCurrencies = {!! json_encode($tradeCurrencies) !!};
    var tradeCurrenciesByIsoCode = {};
    for (let i in tradeCurrencies) {
        tradeCurrenciesByIsoCode[tradeCurrencies[i]['iso_code']] =
            tradeCurrencies[i];
    }

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    var $symbolInput           = $('#symbol-input');
    var $timestampPickerInput  = $('#timestamp-picker>input');
    var $getFinanceData        = $('#get-finance-data');
    var $fetchedSymbolName     = $('#fetched-symbol-name');
    var $fetchedTradeCurrency  = $('#fetched-trade-currency');
    var $fetchedUnitPrice      = $('#fetched-unit-price');
    var $availableQuantity     = $('#available-quantity');

    var $actionSelect          = $('#action-select');
    var $accountSelect         = $('#account-select');

    var $quantityInput         = $('#quantity-input');
    var $editTradeForm         = $('#edit-trade-form');

    $getFinanceData.click(function() {
        $.ajax({
            type: 'GET',
            url:  "{{ url('/get-finance-data') }}",
            data: {
                symbol: $symbolInput.val(),
                timestamp: $timestampPickerInput.val(),

                // Used for SELL
                trade_id: $editTradeForm.find('[name="id"]').val(),
                account_id: $accountSelect.val()
            },
            success: function(data, textStatus, jqXHR) {
                $getFinanceData.addClass('text-success');
                $getFinanceData.removeClass('text-danger');
                $getFinanceData.attr('data-bs-original-title', 'Get Finance Data');

                $fetchedSymbolName.find('span').html(data.name);
                $fetchedSymbolName.show();

                $fetchedTradeCurrency.find('span').text(data.currency);
                $fetchedTradeCurrency.show();

                var $select = $('#trade_currency-select').selectize();
                var selectize = $select[0].selectize;
                selectize.setValue(tradeCurrenciesByIsoCode[data.currency]['id']);

                $fetchedUnitPrice.find('span').text(data.price);
                $fetchedUnitPrice.find('span').attr('data-bs-original-title',
                    data.quote_timestamp);
                $fetchedUnitPrice.show();

                if (data.available_quantity != null) {
                    $availableQuantity.find('span').text(data.available_quantity);
                    $availableQuantity.show();
                    if ($actionSelect.val() == 'SELL') {
                        $quantityInput.attr('max', data.available_quantity);
                    }
                }
                // console.log(data);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $getFinanceData.addClass('text-danger');
                $getFinanceData.removeClass('text-success');
                $getFinanceData.attr('data-bs-original-title',
                    jqXHR.responseJSON.message);

                $fetchedSymbolName.find('span').text('');
                $fetchedSymbolName.hide();

                $fetchedTradeCurrency.find('span').text('');
                $fetchedTradeCurrency.hide();

                $fetchedUnitPrice.find('span').text('');
                $fetchedUnitPrice.find('span').attr('data-bs-original-title', '');
                $fetchedUnitPrice.hide();

                $availableQuantity.find('span').text('');
                $availableQuantity.hide();
                $quantityInput.removeAttr('max');
                // console.log(jqXHR.responseJSON.message);
            }
        });
    });

});
</script>


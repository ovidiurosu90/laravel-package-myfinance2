<script type="module">
$(document).ready(function()
{
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    var $symbolInput             = $('#symbol-input');
    var $timestampPickerInput    = $('#timestamp-picker>input');
    var $getFinanceData          = $('#get-finance-data');
    var $fetchedSymbolName       = $('#fetched-symbol-name');
    var $fetchedDividendCurrency = $('#fetched-dividend-currency');

    $getFinanceData.click(function() {
        $.ajax({
            type: 'GET',
            url:  "{{ url('/get-finance-data') }}",
            data: {
                symbol: $symbolInput.val(),
                timestamp: $timestampPickerInput.val(),
            },
            success: function(data, textStatus, jqXHR) {
                $getFinanceData.addClass('text-success');
                $getFinanceData.removeClass('text-danger');
                $getFinanceData.attr('data-bs-original-title', 'Get Finance Data');

                $fetchedSymbolName.find('span').html(data.name);
                $fetchedSymbolName.show();

                $fetchedDividendCurrency.find('span').text(data.currency);
                $fetchedDividendCurrency.show();

                // Populating the account currency
                var currency = data.currency;
                var currenciesMapping = {!! json_encode(config('general.currencies_mapping')) !!};
                if (currency in currenciesMapping) {
                    currency = currenciesMapping[currency];
                }
                var $select = $('#dividend_currency-select').selectize();
                var selectize = $select[0].selectize;
                selectize.setValue(currency);

                // console.log(data);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $getFinanceData.addClass('text-danger');
                $getFinanceData.removeClass('text-success');
                $getFinanceData.attr('data-bs-original-title', jqXHR.responseJSON.message);

                $fetchedSymbolName.find('span').text('');
                $fetchedSymbolName.hide();

                $fetchedDividendCurrency.find('span').text('');
                $fetchedDividendCurrency.hide();

                // console.log(jqXHR.responseJSON.message);
            }
        });
    });

});
</script>


<script type="module">
$(document).ready(function()
{
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    var $symbolInput           = $('#symbol-input');
    var $timestampPickerInput  = $('#timestamp-picker>input');
    var $getFinanceData        = $('#get-finance-data');
    var $fetchedSymbolName     = $('#fetched-symbol-name');
    var $fetchedSymbolData     = $('#fetched-symbol-data');

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

                $fetchedSymbolData.html(
                    '<p><b>Name</b>: ' + data.name + '</p>' +
                    '<p><b>Price</b>: ' + data.price + ' ' + data.currency + ' on ' + data.quote_timestamp + '</p>' +

                    //NOTE These don't take into account the timestamp
                    '<p><b>52-Wk high</b>: ' + data.fiftyTwoWeekHigh + ' ' + data.currency + '</p>' +
                    '<p><b>52-Wk low</b>: ' + data.fiftyTwoWeekLow + ' ' + data.currency + '</p>' +
                    '<p><b>% Below high</b>: ' + (-data.fiftyTwoWeekHighChangePercent * 100).toFixed(2) + ' %</p>' +
                    '<p><b>% Above low</b>: ' + (data.fiftyTwoWeekLowChangePercent * 100).toFixed(2) + ' %</p>' +
                    ''
                );
                // console.log(data);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $getFinanceData.addClass('text-danger');
                $getFinanceData.removeClass('text-success');
                $getFinanceData.attr('data-bs-original-title', jqXHR.responseJSON.message);

                $fetchedSymbolName.find('span').text('');
                $fetchedSymbolName.hide();

                $fetchedSymbolData.html('<div class="alert alert-danger" role="alert">' + jqXHR.responseJSON.message + '</div>');
                // console.log(jqXHR.responseJSON.message);
            }
        });
    });

});
</script>


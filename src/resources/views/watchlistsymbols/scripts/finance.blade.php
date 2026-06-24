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

    $getFinanceData.click(function()
    {
        $.ajax({
            type: 'GET',
            url:  "{{ url('/get-finance-data') }}",
            data: {
                symbol: $symbolInput.val(),
                timestamp: $timestampPickerInput.val(),
            },
            success: function(data, textStatus, jqXHR)
            {
                $getFinanceData.addClass('text-success');
                $getFinanceData.removeClass('text-danger');
                $getFinanceData.attr('data-bs-original-title', 'Get Finance Data');

                $fetchedSymbolName.find('span').html(data.name);
                $fetchedSymbolName.show();

                // Primary 52-week figures are closing-based (highest / lowest daily close over the
                // past year, dated); Yahoo's intraday high/low is shown beneath as a dimmed
                // secondary. Falls back to intraday only when no closing range is available.
                var px = parseFloat(data.price);
                var cHigh = data.closingHigh, cLow = data.closingLow;
                var pctBelowCHigh = (cHigh != null && cHigh > 0)
                    ? ((cHigh - px) / cHigh * 100).toFixed(2) : null;
                var pctAboveCLow = (cLow != null && cLow > 0)
                    ? ((px - cLow) / cLow * 100).toFixed(2) : null;
                var intradayHtml = (data.fiftyTwoWeekHigh != null && data.fiftyTwoWeekLow != null)
                    ? '<p style="opacity:0.55;font-style:italic;"><b>Intraday (Yahoo)</b>: high '
                        + data.fiftyTwoWeekHigh + ' / low ' + data.fiftyTwoWeekLow + ' '
                        + data.currency + ' (single intraday extremes, no date)</p>'
                    : '';

                var rangeHtml;
                if (cHigh != null && cLow != null) {
                    rangeHtml =
                        '<p><b>52-Wk closing high</b>: ' + cHigh + ' ' + data.currency
                            + (data.closingHighDate ? ' on ' + data.closingHighDate : '') + '</p>' +
                        '<p><b>52-Wk closing low</b>: ' + cLow + ' ' + data.currency
                            + (data.closingLowDate ? ' on ' + data.closingLowDate : '') + '</p>' +
                        (pctBelowCHigh != null
                            ? '<p><b>% Below closing high</b>: ' + pctBelowCHigh + ' %</p>' : '') +
                        (pctAboveCLow != null
                            ? '<p><b>% Above closing low</b>: ' + pctAboveCLow + ' %</p>' : '') +
                        intradayHtml;
                } else {
                    rangeHtml =
                        '<p><b>52-Wk high</b>: ' + data.fiftyTwoWeekHigh + ' '
                            + data.currency + '</p>' +
                        '<p><b>52-Wk low</b>: ' + data.fiftyTwoWeekLow + ' '
                            + data.currency + '</p>' +
                        '<p><b>% Below high</b>: '
                            + (-data.fiftyTwoWeekHighChangePercent * 100).toFixed(2) + ' %</p>' +
                        '<p><b>% Above low</b>: '
                            + (data.fiftyTwoWeekLowChangePercent * 100).toFixed(2) + ' %</p>';
                }

                $fetchedSymbolData.html(
                    '<p><b>Name</b>: ' + data.name + '</p>' +
                    '<p><b>Price</b>: ' + data.price + ' ' + data.currency
                        + ' on ' + data.quote_timestamp + '</p>' +
                    rangeHtml
                );
                // console.log(data);
            },
            error: function(jqXHR, textStatus, errorThrown)
            {
                $getFinanceData.addClass('text-danger');
                $getFinanceData.removeClass('text-success');
                $getFinanceData.attr('data-bs-original-title',
                    jqXHR.responseJSON.message);

                $fetchedSymbolName.find('span').text('');
                $fetchedSymbolName.hide();

                $fetchedSymbolData.html(
                    '<div class="alert alert-danger" role="alert">'
                    + jqXHR.responseJSON.message
                    + '</div>');
                // console.log(jqXHR.responseJSON.message);
            }
        });
    });

    if ($symbolInput.val()) {
        $getFinanceData.trigger('click');
    }

});
</script>


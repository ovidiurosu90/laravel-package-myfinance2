<script type="module">
$(document).ready(function()
{
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    var $timestampInput = $('#timestamp-picker>input');
    var $accountSelect = $('#account-select');
    var $descriptionTextarea = $('#description');
    var $infoHeader = $('#info-header');
    var $amount = $('#amount');

    $timestampInput.on('change', function()
    {
        inputChange();
    });
    $accountSelect.change(function()
    {
        inputChange();
    });
    $descriptionTextarea.bind('input propertychange', function()
    {
        // console.log('description changed');

        // remove spaces
        var numericFormula = $descriptionTextarea.val().replace(/ /g, '');

        // remove invalid '.'
        numericFormula = numericFormula.replace(/[a-zA-Z]\.[a-zA-Z]/g, '');

        // remove invalid '-'
        numericFormula = numericFormula.replace(/[a-zA-Z]\-[a-zA-Z]/g, '');

        // remove non-numeric
        numericFormula = numericFormula.replace(/[^\d.\-\+]/g, '');

        //LATER Remove this after testing
        console.log('Numeric Formula: ' + numericFormula);

        var amount = 0.0;
        try {
            amount = eval(numericFormula);
            $amount.val(amount.toFixed(2));
            $descriptionTextarea.css('border', '');
        } catch (error) {
            console.log(error);
            $descriptionTextarea.css('border', '2px solid #f00');
        }
    });

    var lastAttempt = '';

    var inputChange = function()
    {
        var timestamp = $timestampInput.val();
        var account_id = $accountSelect[0].selectize.getValue();
        var description = $descriptionTextarea.val();

        if (!timestamp || !account_id || description) {
            return;
        }
        var attemptKey = timestamp + '_' + account_id;
        if (attemptKey == lastAttempt) { // Avoid making duplicate requests
            return;
        }
        lastAttempt = attemptKey;

        $.ajax({
            type: 'GET',
            url:  "{{ url('/get-cash-balances') }}",
            data: {
                timestamp: timestamp,
                account_id: account_id,
            },
            success: function(data, textStatus, jqXHR)
            {
                // console.log(data);
                if (data && 'cash_balances' in data && Array.isArray(data['cash_balances'])) {
                    for (let i = 0; i < data['cash_balances'].length; i++) {
                        description += data['cash_balances'][i];
                        if (i != data['cash_balances'].length - 1) {
                            description += ";\n";
                        }
                    }
                    $descriptionTextarea.val(description);
                    $descriptionTextarea.trigger('input');
                }
                $infoHeader.html("");
            },
            error: function(jqXHR, textStatus, errorThrown)
            {
                $infoHeader.html('<i class="btn p-0 fa fa-exclamation" ' +
                    'data-bs-toggle="tooltip" title="Error: ' + errorThrown +
                    '! ' + jqXHR.responseJSON.message +
                    '" style="font-size: 16px; margin-top: -4px;"></i>');
            }
        });

        // console.log('inputChange! timestamp: ' + timestamp + ', account_id: ' +
        //     account_id);
    };

});
</script>


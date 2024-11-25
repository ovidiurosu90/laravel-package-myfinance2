<script type="module">
$(document).ready(function ()
{
    var $debitCurrencySelect = $("#transaction-debit_currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.debit_currency.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value) {
            currencyChange();
        }
    });
    var debitCurrencySelectize = $debitCurrencySelect[0] ? $debitCurrencySelect[0].selectize : null;

    var $creditCurrencySelect = $("#transaction-credit_currency-select").selectize({
        placeholder: ' {{ trans('myfinance2::ledger.forms.transaction-form.credit_currency.placeholder') }} ',
        allowClear: true,
        create: true,
        highlight: true,
        diacritics: true,
        onChange: function(value) {
            currencyChange();
        }
    });
    var creditCurrencySelectize = $creditCurrencySelect[0] ? $creditCurrencySelect[0].selectize : null;

    var $exchangeRateInput = $('input#exchange_rate');
    var $amountInput = $('input#amount');
    var $feeInput = $('input#fee');
    var $estimateGainButton = $('#estimate-gain-button');
    var $estimatedCost = $('#estimated-cost');
    var $estimatedAmount = $('#estimated-amount');
    var $estimatedGain = $('#estimated-gain');

    var currencyChange = function() {
        debitCurrency = debitCurrencySelectize.getValue();
        creditCurrency = creditCurrencySelectize.getValue();
        if (debitCurrency && creditCurrency && debitCurrency == creditCurrency) {
            $exchangeRateInput.val(1);
        }
    };

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $estimateGainButton.click(function() {
        $.ajax({
            type: 'GET',
            url:  "{{ url('/get-currency-exchange-gain-estimate') }}",
            data: {
                debit_currency: debitCurrencySelectize.getValue(),
                credit_currency: creditCurrencySelectize.getValue(),
                exchange_rate: $exchangeRateInput.val(),
                amount: $amountInput.val(),
                fee: $feeInput.val(),
            },
            success: function(data, textStatus, jqXHR) {
                $estimateGainButton.addClass('text-success');
                $estimateGainButton.removeClass('text-danger');
                $estimateGainButton.attr('data-bs-original-title', 'Get Currency Exchange Gain Estimate');

                $estimatedCost.html(data.formatted_cost);
                $estimatedAmount.html(data.formatted_credit_amount);
                $estimatedGain.html(data.formatted_gain);

                // console.log(data);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $estimateGainButton.addClass('text-danger');
                $estimateGainButton.removeClass('text-success');

                var title = jqXHR.responseJSON.message;
                if (jqXHR.responseJSON.errors) {
                    for (let [key, value] of Object.entries(jqXHR.responseJSON.errors)) {
                        title += "\n" + key + ": " + value;
                    }
                }

                $estimateGainButton.attr('data-bs-original-title', title);

                $estimatedCost.text('');
                $estimatedAmount.text('');
                $estimatedGain.text('');

                // console.log(jqXHR.responseJSON.message);
            }
        });
    });
});
</script>


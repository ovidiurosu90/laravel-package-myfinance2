<style>
#fee_currency-toggle_container .btn-default {
    color: #333;
    background-color: #fff;
    border-color: #ccc
}
#fee_currency-toggle_container .btn.disabled,
#fee_currency-toggle_container .btn[disabled],
#fee_currency-toggle_container fieldset[disabled] .btn {
    pointer-events:none;
    cursor:not-allowed;
    filter:alpha(opacity=65);
    -webkit-box-shadow:none;
    box-shadow:none;
    opacity:.65
}
</style>
<script type="module">
$(document).ready(function ()
{
    var $feeCurrencyToggle = $("#fee_currency-toggle");
    var $exchangeRateInput = $("#exchange_rate");
    var $feeInput = $("#fee");
    var $feeDividendCurrencyInput = $("#fee_dividend_currency");

    var $accountCurrencyLabelTooltip = $("#account_currency-label-tooltip");

    $feeCurrencyToggle.on("my-reset", function() {
        $feeCurrencyToggle.bootstrapToggle('destroy');
        $feeCurrencyToggle.bootstrapToggle();

        if ($feeCurrencyToggle.attr("data-onlabel")  != "x" &&
            $feeCurrencyToggle.attr("data-offlabel") != "x" &&
            $feeCurrencyToggle.attr("data-onlabel")  != $feeCurrencyToggle.attr("data-offlabel")
        ) {
            $feeCurrencyToggle.bootstrapToggle('enable');
        } else {
            $feeCurrencyToggle.bootstrapToggle('disable');
        }
    });
    $feeCurrencyToggle.trigger("my-reset");

    $feeCurrencyToggle.change(function() {
        $feeInput.toggle();
        $feeDividendCurrencyInput.toggle();
        if ($feeCurrencyToggle.prop('checked')) {
            $accountCurrencyLabelTooltip.html($feeCurrencyToggle.attr("data-onlabel"));
        } else {
            $accountCurrencyLabelTooltip.html($feeCurrencyToggle.attr("data-offlabel"));
        }
    });

    $feeInput.change(function() {
        if ($feeInput.val() && $exchangeRateInput.val()) {
            var value = $feeInput.val() * $exchangeRateInput.val();
            $feeDividendCurrencyInput.val(value.toFixed(2));
        } else {
            $feeDividendCurrencyInput.val("");
        }
    });

    $feeDividendCurrencyInput.change(function() {
        if ($feeDividendCurrencyInput.val() && $exchangeRateInput.val()) {
            var value = $feeDividendCurrencyInput.val() / $exchangeRateInput.val();
            $feeInput.val(value.toFixed(2));
        } else {
            $feeInput.val("");
        }
    });

    $exchangeRateInput.change(function() {
        $feeInput.val("");
        $feeDividendCurrencyInput.val("");
    });
});
</script>


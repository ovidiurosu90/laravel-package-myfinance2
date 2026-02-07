<script type="module">
// ========== Helper Functions ==========

/**
 * Reinitialize Bootstrap tooltip for an element
 */
function reinitializeTooltip($element)
{
    if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Tooltip) {
        var tooltipInstance = window.bootstrap.Tooltip.getInstance($element[0]);
        if (tooltipInstance) {
            tooltipInstance.dispose();
        }
        new window.bootstrap.Tooltip($element[0]);
    }
}

/**
 * Apply color formatting to a value based on sign
 */
function applyColorFormatting(value, numericValue)
{
    if (numericValue < 0) {
        return '<span class="text-danger">- ' + value + '</span>';
    } else if (numericValue > 0) {
        return '<span class="text-success">+ ' + value + '</span>';
    }
    return value;
}

/**
 * Extract currency-specific data from cell
 */
function getCellData($td, isEUR)
{
    return {
        value: isEUR ? $td.data('eur') : $td.data('usd'),
        tooltip: isEUR ? $td.data('eur-tooltip') : $td.data('usd-tooltip'),
        showWarning: isEUR
            ? ($td.data('eur-show-warning') === 'true' || $td.data('eur-show-warning') === true)
            : ($td.data('usd-show-warning') === 'true' || $td.data('usd-show-warning') === true),
        override: isEUR ? $td.data('eur-override') : $td.data('usd-override'),
        calculated: isEUR ? $td.data('eur-calculated') : $td.data('usd-calculated'),
        fees: isEUR ? $td.data('eur-fees') : $td.data('usd-fees'),
        feesText: isEUR ? $td.data('eur-fees-text') : $td.data('usd-fees-text'),
        numericValue: isEUR ? parseFloat($td.data('eur-value')) : parseFloat($td.data('usd-value'))
    };
}

/**
 * Update return/gain value cell with coloring
 */
function updateReturnValueCell($td, cellData)
{
    var $tooltipSpan = $td.find('span[data-bs-toggle="tooltip"].principal-amount-value');
    var coloredValue = applyColorFormatting(cellData.value, cellData.numericValue);

    if ($tooltipSpan.length > 0) {
        // Preserve tooltip span, update content and tooltip
        $tooltipSpan.html(coloredValue);
        $tooltipSpan.attr('data-bs-title', cellData.tooltip);
        reinitializeTooltip($tooltipSpan);
    } else {
        // Standard return value without tooltip
        // Check if there's a regular tooltip span (e.g., for returns row)
        var $regularTooltipSpan = $td.find('span[data-bs-toggle="tooltip"]');

        if ($regularTooltipSpan.length > 0) {
            // Check if this cell has override data
            if (cellData.override || cellData.calculated) {
                // Has override - use override display logic
                updateOverrideDisplay($td, $regularTooltipSpan, cellData);
            } else {
                // No override - just update with colored value
                $regularTooltipSpan.html(coloredValue);
                if (cellData.tooltip) {
                    $regularTooltipSpan.attr('data-bs-title', cellData.tooltip);
                    reinitializeTooltip($regularTooltipSpan);
                }
            }
        } else {
            // NOTE: This business logic must stay in the frontend because:
            // - We store plain formatted values (e.g., "217,867.47 €") in data attributes
            // - HTML stored in attributes gets escaped by the browser (< > become &lt; &gt;)
            // - Pre-calculated colored HTML would be lost when retrieved from data attributes
            // - The backend provides numeric values, frontend applies the color decision
            // This is a calculated presentation layer concern based on the numeric value,
            // not core business logic, so this exception is acceptable.
            $td.html(coloredValue);
        }
    }
}

/**
 * Update cell with fees text (total rows)
 */
function updateFeesTextCell($td, cellData)
{
    var $firstDiv = $td.find('div').first();
    if ($firstDiv.length > 0) {
        var completeHtml = cellData.value;
        if (cellData.feesText) {
            completeHtml += '<span style="font-size: 0.85rem; color: #6c757d; ' +
                'margin-left: 0.25rem;" class="fees-text">' +
                cellData.feesText + '</span>';
        }
        $firstDiv.html(completeHtml);
    }
}

/**
 * Update override/calculated display within a cell
 */
function updateOverrideDisplay($td, $span, cellData)
{
    // Support multiple override types: dividends, deposits, withdrawals, returns
    var $overrideIcon = $td.find(
        '.dividends-override-icon, .deposits-override-icon, .withdrawals-override-icon, .return-override-icon'
    );
    var $smallOverride = $td.find(
        'small.dividends-calculated-value, small.deposits-calculated-value, ' +
        'small.withdrawals-calculated-value, small.return-calculated-value'
    );

    if (cellData.override) {
        // Update span with override value
        $span.html(cellData.override);

        // Show and update the calculated value if element exists
        if ($smallOverride.length > 0) {
            $smallOverride.html('(Calculated: ' + cellData.calculated + ')');
            $smallOverride.show();
        }

        // Show the icon if element exists
        if ($overrideIcon.length > 0) {
            $overrideIcon.show();
        }
    } else {
        // Update span with regular value
        $span.html(cellData.value);

        // Hide override elements if they exist
        if ($smallOverride.length > 0) {
            $smallOverride.hide();
        }
        if ($overrideIcon.length > 0) {
            $overrideIcon.hide();
        }
    }
}

/**
 * Update regular cell with tooltips and optional override display
 */
function updateRegularCell($td, cellData)
{
    var $span = $td.find('span[data-bs-toggle="tooltip"]');

    if ($span.length > 0) {
        $span.html(cellData.value);
        $span.attr('data-bs-title', cellData.tooltip);
        reinitializeTooltip($span);

        // Handle override/calculated value display
        updateOverrideDisplay($td, $span, cellData);
    } else {
        // Fallback for cells without tooltip span
        // Try to find a regular span (e.g., for withdrawals)
        var $regularSpan = $td.find('span').first();

        if ($regularSpan.length > 0) {
            // Handle override display for regular spans
            updateOverrideDisplay($td, $regularSpan, cellData);
        } else {
            // No span at all - create full HTML
            var html = cellData.override
                ? cellData.override + '<i class="fa-solid fa-circle-info ms-1 dividends-override-icon" ' +
                  'style="font-size: 0.75rem; color: black;" data-bs-toggle="tooltip" data-bs-placement="top" ' +
                  'data-bs-title="This value has been overridden to match the annual statement."></i>' +
                  '<small style="color: #6c757d; margin-left: 0.5rem;" class="dividends-calculated-value">' +
                  '(Calculated: ' + cellData.calculated + ')</small>'
                : cellData.value;
            $td.html(html);
        }
    }
}

/**
 * Update a single currency cell
 */
function updateCurrencyCell($td, isEUR)
{
    var cellData = getCellData($td, isEUR);
    var isReturnValue = !isNaN(cellData.numericValue);
    var hasFeesText = $td.find('.fees-text').length > 0;

    if (isReturnValue && !hasFeesText) {
        // Return/gain value with coloring
        updateReturnValueCell($td, cellData);
    } else if (hasFeesText) {
        // Total cell with fees text
        updateFeesTextCell($td, cellData);
    } else {
        // Regular cell with tooltips
        updateRegularCell($td, cellData);
    }

    // Update warning icon visibility
    var $warningIcon = $td.find('.dividend-warning-icon, .purchase-warning-icon, .sale-warning-icon');
    if ($warningIcon.length > 0) {
        $warningIcon.css('display', cellData.showWarning ? 'inline' : 'none');
    }

    // Update fee display
    var $feeDisplay = $td.find('.fee-display');
    if ($feeDisplay.length > 0) {
        $feeDisplay.html(cellData.fees);
    }

    // Update transaction fees text (deposits/withdrawals header suffix)
    var $transactionFeesText = $td.find('.transaction-fees-text');
    if ($transactionFeesText.length > 0) {
        if (cellData.feesText) {
            $transactionFeesText.html(cellData.feesText);
            $transactionFeesText.show();
        } else {
            $transactionFeesText.hide();
        }
    }
}

/**
 * Update table headers to show current currency
 */
function updateTableHeaders(currency)
{
    $('th').each(function()
    {
        var text = $(this).text().trim();
        if (text.startsWith('Value in')) {
            $(this).text('Value in ' + currency);
        } else if (text.startsWith('Principal Amount in')) {
            $(this).text('Principal Amount in ' + currency);
        } else if (text.startsWith('Fee in')) {
            $(this).text('Fee in ' + currency);
        } else if (text.startsWith('Gross Amount in')) {
            $(this).text('Gross Amount in ' + currency);
        } else if (text.startsWith('Amount in')) {
            $(this).text('Amount in ' + currency);
        }
    });
}

/**
 * Apply initial coloring to all return values
 */
function applyInitialColoring(selectedCurrency)
{
    var isEUR = selectedCurrency === 'EUR';

    // Color principal amounts (trades)
    $('.principal-amount-value').each(function()
    {
        var $span = $(this);
        var $td = $span.closest('.currency-value');
        var numericValue = isEUR ? parseFloat($td.data('eur-value')) : parseFloat($td.data('usd-value'));

        if (!isNaN(numericValue)) {
            var newValue = isEUR ? $td.data('eur') : $td.data('usd');
            var coloredValue = applyColorFormatting(newValue, numericValue);
            $span.html(coloredValue);
        }
    });

    // Color all other return values (dividends, returns, etc.)
    $('.currency-value').each(function()
    {
        var $td = $(this);
        var numericValue = isEUR ? parseFloat($td.data('eur-value')) : parseFloat($td.data('usd-value'));

        // Skip if no numeric value or already has principal-amount-value (handled above)
        if (isNaN(numericValue) || $td.find('.principal-amount-value').length > 0) {
            return;
        }

        // Skip cells with fees text (totals row)
        if ($td.find('.fees-text').length > 0) {
            return;
        }

        var cellData = getCellData($td, isEUR);

        // Find the innermost span (the one with the actual value)
        var $spans = $td.find('span[data-bs-toggle="tooltip"]');
        if ($spans.length > 0) {
            var $tooltipSpan = $spans.first();
            var $innerSpan = $tooltipSpan.find('span').first();

            if ($innerSpan.length > 0) {
                // Apply coloring to inner span
                var coloredValue = applyColorFormatting(cellData.value, cellData.numericValue);
                $innerSpan.replaceWith(coloredValue);
            }
        }
    });
}

// ========== Main Initialization ==========

$(document).ready(function()
{
    // Initialize Bootstrap Toggle
    if ($.fn.bootstrapToggle) {
        $('#toggle-currency-select').bootstrapToggle();
    }

    // Apply initial coloring
    var selectedCurrency = '{{ $selectedCurrency ?? "EUR" }}';
    applyInitialColoring(selectedCurrency);

    // Trigger currency toggle if USD is selected
    if (selectedCurrency === 'USD' && $('#toggle-currency-select').is(':checked')) {
        $('#toggle-currency-select').click();
    }

    // Handle currency toggle change
    $('#toggle-currency-select').change(function()
    {
        var isEUR = $(this).prop('checked');
        var currency = isEUR ? 'EUR' : 'USD';

        // Update URL parameter
        var url = new URL(window.location);
        url.searchParams.set('currency_iso_code', currency);
        window.history.pushState({}, '', url);

        // Update all currency cells
        $('.currency-value').each(function()
        {
            updateCurrencyCell($(this), isEUR);
        });

        // Update table headers
        updateTableHeaders(currency);
    });
});
</script>

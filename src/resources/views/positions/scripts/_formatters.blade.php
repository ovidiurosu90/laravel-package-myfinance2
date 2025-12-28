{{-- Shared Chart Formatters

     This partial defines the formatter functions used by both account and user
     overview charts. Including this file ensures consistent formatting across all charts.

     Functions defined:
     - createPercentageFormatter(): Format numbers as percentages (e.g., 10.5%)
     - createCurrencyFormatter(locale): Format numbers as currency using Intl API
--}}

// Shared chart formatters - keep formatters in one place for consistency
function createPercentageFormatter()
{
    return (price) => {
        // Ensure 2 decimal places for percentage values
        return (Math.round(price * 100) / 100).toFixed(2) + '%';
    };
}

function createCurrencyFormatter(locale = 'de-DE')
{
    return (price, currency) => {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: currency,
        }).format(price);
    };
}


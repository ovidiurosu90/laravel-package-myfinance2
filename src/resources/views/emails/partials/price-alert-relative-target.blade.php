@use('ovidiuro\myfinance2\App\Services\MoneyFormat')
{{-- Relative-target line for the price alert emails: "5% below 52W high (ref 123.45 USD on
     12 Mar 2026)". Expects: $alert (PriceAlert), $currency (plain-text currency label).
     Renders nothing for a FIXED alert. --}}
@if (!empty($alert) && $alert->isRelative())
    @php
        $relativeText = $alert->getRelativeTargetLabel();
        if ($alert->reference_price !== null) {
            $reference = trim(MoneyFormat::get_formatted_price((float) $alert->reference_price, true)
                . ' ' . ($currency ?? ''));
            if ($alert->reference_date !== null) {
                $reference .= ' on ' . $alert->reference_date->format('d M Y');
            }
            $relativeText .= " (ref {$reference})";
        }
    @endphp
    <div style="font-size:12px;color:#6c757d;font-weight:normal;">{{ $relativeText }}</div>
@endif

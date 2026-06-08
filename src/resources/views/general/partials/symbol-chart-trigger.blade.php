@php
    // Expand-chart trigger icon. Opens the shared symbol chart modal for one symbol.
    // Stat values are passed already formatted (server-side) so the modal never
    // formats numbers itself; they may contain HTML (e.g. coloured spans) and are
    // emitted through {{ }} so the attribute stays well-formed and the browser
    // hands the original markup back to JS (injected via .html()).
    $sct_symbol         = $symbol         ?? '';
    $sct_symbolName     = $symbolName     ?? $sct_symbol;
    $sct_currency       = isset($currency)
        ? html_entity_decode($currency, ENT_QUOTES | ENT_HTML5)
        : '';
    $sct_baseValue      = $baseValue      ?? '';
    $sct_accountId      = $accountId      ?? '';
    $sct_mvalue         = $mvalue         ?? '';
    $sct_costBasis      = $costBasis      ?? '';
    $sct_avgCost        = $avgCost        ?? '';
    $sct_dayGain        = $dayGain        ?? '';
    $sct_dayGainPct     = $dayGainPct     ?? '';
    $sct_overallGain    = $overallGain    ?? '';
    $sct_overallGainPct = $overallGainPct ?? '';

    // Pre-rendered "At close / Pre-market" header shown atop the modal chart.
    $sct_quoteHeader = !empty($quoteHeaderData)
        ? view('myfinance2::general.partials.quote-header', $quoteHeaderData)->render()
        : '';
@endphp
<i class="fas fa-up-right-and-down-left-from-center symbol-chart-expand text-secondary"
   role="button"
   style="cursor: pointer; font-size: 0.8em;"
   data-bs-toggle="modal"
   data-bs-target="#symbol-chart-modal"
   title="Expand chart"
   data-symbol="{{ $sct_symbol }}"
   data-symbol-name="{{ $sct_symbolName }}"
   data-currency="{{ $sct_currency }}"
   data-base-value="{{ $sct_baseValue }}"
   data-account-id="{{ $sct_accountId }}"
   data-mvalue="{{ $sct_mvalue }}"
   data-cost-basis="{{ $sct_costBasis }}"
   data-avg-cost="{{ $sct_avgCost }}"
   data-day-gain="{{ $sct_dayGain }}"
   data-day-gain-pct="{{ $sct_dayGainPct }}"
   data-overall-gain="{{ $sct_overallGain }}"
   data-overall-gain-pct="{{ $sct_overallGainPct }}"
   data-quote-header="{{ $sct_quoteHeader }}"></i>

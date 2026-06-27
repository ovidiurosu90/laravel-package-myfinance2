@php
    use ovidiuro\myfinance2\App\Services\MoneyFormat;

    // Three columns shared by the watchlist and non-watchlist tables: "52W Range", "% Low", "% High".
    // Primary (and the value the table sorts / filters on) is the closing-based 52-week range built
    // in the BE (highest / lowest daily close over the past year, native currency, dated). In the
    // "% Low" / "% High" columns, Yahoo's 52-week intraday distance is shown beneath as a dimmed
    // secondary with a tooltip explaining how it differs; Yahoo provides no date for it.
    //
    // Tooltips sit on the inner text span (not the cell or block wrapper) so the hover box anchors
    // to the figure rather than the full-width cell.
    $code      = $quoteData['tradeCurrencyModel']->display_code;
    $codePlain = html_entity_decode($code, ENT_QUOTES | ENT_HTML5);
    $cr        = $quoteData['table_meta']['closing_range'] ?? null;
    $hasOpen   = count($quoteData['open_positions']) > 0;

    // Current (live) price the "% Low" / "% High" figures are measured from. Shown in the tooltips,
    // with the explicit division, so the percentage can be verified against the closing / intraday
    // high and low below. This is the same pre/post-market-aware price as the row's Price column.
    $curPrice      = (isset($quoteData['price']) && $quoteData['price'] !== '')
        ? (float) $quoteData['price'] : null;
    $curPriceNum   = $curPrice !== null ? MoneyFormat::get_formatted_price_plain($curPrice) : '';
    $curPriceLabel = $curPrice !== null ? $curPriceNum . ' ' . $codePlain : '';

    $intraHigh    = $quoteData['fiftyTwoWeekHigh'] ?? null;
    $intraLow     = $quoteData['fiftyTwoWeekLow'] ?? null;
    $intraHighPct = isset($quoteData['fiftyTwoWeekHighChangePercent'])
        ? - $quoteData['fiftyTwoWeekHighChangePercent'] * 100 : null;
    $intraLowPct  = isset($quoteData['fiftyTwoWeekLowChangePercent'])
        ? $quoteData['fiftyTwoWeekLowChangePercent'] * 100 : null;

    $hasClosing = $cr !== null && $cr['low_native'] !== null && $cr['high_native'] !== null;

    // Sort on the closing-based figure when present, else fall back to the intraday one.
    $lowOrder  = ($cr['low_pct']  ?? null) ?? $intraLowPct  ?? -9999999;
    $highOrder = ($cr['high_pct'] ?? null) ?? $intraHighPct ?? -9999999;

    $intraHighTip = $intraHigh !== null
        ? 'Yahoo 52-week intraday high (' . MoneyFormat::get_formatted_price_plain($intraHigh)
            . ' ' . $codePlain . '): the single highest price touched intraday in the past year. It '
            . 'can sit above the highest daily close when the high was a brief intraday spike. '
            . 'Yahoo does not provide the date it occurred.'
            . ($curPrice !== null && $intraHighPct !== null
                ? ' Current price ' . $curPriceLabel . ' is '
                    . MoneyFormat::get_formatted_pct($intraHighPct) . '% below it.'
                : '')
        : '';
    $intraLowTip = $intraLow !== null
        ? 'Yahoo 52-week intraday low (' . MoneyFormat::get_formatted_price_plain($intraLow)
            . ' ' . $codePlain . '): the single lowest price touched intraday in the past year. It '
            . 'can sit below the lowest daily close when the low was a brief intraday dip. '
            . 'Yahoo does not provide the date it occurred.'
            . ($curPrice !== null && $intraLowPct !== null
                ? ' Current price ' . $curPriceLabel . ' is '
                    . MoneyFormat::get_formatted_pct($intraLowPct) . '% above it.'
                : '')
        : '';
@endphp
{{-- 52W Range: closing low/high only (primary). Falls back to the intraday range when the symbol
     has no closing history yet. --}}
<td class="text-right">
    @if($hasClosing)
        <div class="text-nowrap">
            <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                  title="Lowest daily close over the past year, on {{ $cr['low_date'] }}. This is the primary 52-week low; the table sorts and filters on it.">{!! MoneyFormat::get_formatted_balance($code, $cr['low_native']) !!}</span>
        </div>
        <div class="text-nowrap">
            <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                  title="Highest daily close over the past year, on {{ $cr['high_date'] }}. This is the primary 52-week high; the table sorts and filters on it.">{!! MoneyFormat::get_formatted_balance($code, $cr['high_native']) !!}</span>
        </div>
    @else
        @if($intraLow !== null)
            <div class="text-nowrap">
                <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                      title="Yahoo 52-week intraday low (no closing-price history available yet).">{!! MoneyFormat::get_formatted_balance($code, $intraLow) !!}</span>
            </div>
        @endif
        @if($intraHigh !== null)
            <div class="text-nowrap">
                <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                      title="Yahoo 52-week intraday high (no closing-price history available yet).">{!! MoneyFormat::get_formatted_balance($code, $intraHigh) !!}</span>
            </div>
        @endif
    @endif
</td>
{{-- % Low: closing-based distance above the low (primary) + Yahoo intraday distance (secondary) --}}
<td class="text-right text-nowrap" data-order="{{ $lowOrder }}">
    @if($cr && $cr['low_pct'] !== null)
        <div>
            <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                  title="How far the current price ({{ $curPriceLabel }}) is above the lowest daily close of the past year ({{ MoneyFormat::get_formatted_price_plain($cr['low_native']) }} {{ $codePlain }} on {{ $cr['low_date'] }}). {{ MoneyFormat::get_formatted_pct($cr['low_pct']) }}% = (current {{ $curPriceNum }} {{ $codePlain }} - low {{ MoneyFormat::get_formatted_price_plain($cr['low_native']) }} {{ $codePlain }}) / low. The table sorts and filters on this closing-based figure.">{!! MoneyFormat::get_formatted_52wk_low_percentage($cr['low_pct']) !!}</span>
        </div>
        @if($intraLowPct !== null)
            <div class="fst-italic" style="opacity: 0.55">
                <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                      title="{{ $intraLowTip }}">{!! MoneyFormat::get_formatted_52wk_low_percentage($intraLowPct) !!}</span>
            </div>
        @endif
    @elseif($intraLowPct !== null)
        <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
              title="{{ $intraLowTip }}">{!! MoneyFormat::get_formatted_52wk_low_percentage($intraLowPct) !!}</span>
    @endif
</td>
{{-- % High: closing-based distance below the high (primary) + Yahoo intraday distance (secondary) --}}
<td class="text-right text-nowrap" data-order="{{ $highOrder }}">
    @if($cr && $cr['high_pct'] !== null)
        <div>
            <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                  title="How far the current price ({{ $curPriceLabel }}) is below the highest daily close of the past year ({{ MoneyFormat::get_formatted_price_plain($cr['high_native']) }} {{ $codePlain }} on {{ $cr['high_date'] }}). {{ MoneyFormat::get_formatted_pct($cr['high_pct']) }}% = (high {{ MoneyFormat::get_formatted_price_plain($cr['high_native']) }} {{ $codePlain }} - current {{ $curPriceNum }} {{ $codePlain }}) / high. The table sorts and filters on this closing-based figure.">{!! MoneyFormat::get_formatted_52wk_high_percentage($cr['high_pct'], $hasOpen) !!}</span>
        </div>
        @if($intraHighPct !== null)
            <div class="fst-italic" style="opacity: 0.55">
                <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
                      title="{{ $intraHighTip }}">{!! MoneyFormat::get_formatted_52wk_high_percentage($intraHighPct, $hasOpen) !!}</span>
            </div>
        @endif
    @elseif($intraHighPct !== null)
        <span data-bs-toggle="tooltip" data-bs-custom-class="big-tooltips3"
              title="{{ $intraHighTip }}">{!! MoneyFormat::get_formatted_52wk_high_percentage($intraHighPct, $hasOpen) !!}</span>
    @endif
</td>

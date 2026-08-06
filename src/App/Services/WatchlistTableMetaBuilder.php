<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * Attaches a 'table_meta' array to each watchlist dashboard item with the derived values the
 * items-table needs for sorting and filtering, plus the basis gain figure the per-row tier line
 * displays. Kept in the BE so the blade reads ready-made values instead of computing them.
 */
class WatchlistTableMetaBuilder
{
    private const ORDER_FLOOR = -9999999;

    /**
     * @param array $items symbol => quoteData (with 'performance' and 'categorization')
     * @return array same items, each with an added 'table_meta' key
     */
    /**
     * @param array $items        symbol => quote/categorization data
     * @param array $liveEurRates trade-currency ISO => EUR rate (native * rate = EUR), used to build
     *                            the current-price label alongside the peak labels.
     */
    public function attach(array $items, array $liveEurRates = []): array
    {
        foreach ($items as $symbol => $quoteData) {
            $perf = $quoteData['performance'] ?? [];
            $cat  = $quoteData['categorization'] ?? null;

            [$gainYOrder, $gainYPctOrder] = $this->_gainYOrder($perf);
            [$quadrantLabels, $actionLabels] = $this->_filterLabels($cat);

            // Built first so the gain tooltip's "latest" point reuses the same current-price label
            // (and today's date) the "From peak" / "P&L at peak" columns show.
            $currentPriceLabel = $this->_currentPriceLabel($quoteData, $liveEurRates, $cat);

            $items[$symbol]['table_meta'] = [
                'tier_text'           => TierCalculationService::tierLabel($cat['effective_tier'] ?? null) ?? '',
                'quadrant_labels'     => $quadrantLabels,
                'action_labels'       => $actionLabels,
                'gain_y_order'        => $gainYOrder,
                'gain_y_pct_order'    => $gainYPctOrder,
                'basis_gain_eur'      => $this->_basisGainEur($cat, $perf),
                'peak_labels'         => $this->_peakLabels($quoteData, $cat),
                'gain_windows'        => $this->_gainWindows($quoteData, $cat, $liveEurRates, $currentPriceLabel),
                'closing_range'       => $this->_closingRange($quoteData, $cat),
                'current_price_label' => $currentPriceLabel,
                'has_near_peak'       => $this->_hasNearPeak($cat),
            ];
        }

        return $items;
    }

    /**
     * Sort keys for the hidden Gain/y € and Gain/y % columns. The overall row drives the sort
     * when there is no open window or more than one window; otherwise the single open window does.
     *
     * @return array{0: float, 1: float} [gainYOrder, gainYPctOrder]
     */
    private function _gainYOrder(array $perf): array
    {
        $hasData = !empty($perf['has_data']);

        $openWin = null;
        if ($hasData) {
            foreach ($perf['windows'] ?? [] as $w) {
                if ($w['is_open']) {
                    $openWin = $w;
                    break;
                }
            }
        }

        $source = ($hasData && ($openWin === null || ($perf['window_count'] ?? 0) > 1))
            ? $perf
            : $openWin;

        if ($source === null) {
            return [(float) self::ORDER_FLOOR, (float) self::ORDER_FLOOR];
        }

        $gainYOrder = round(
            $source['annualized_gain_eur'] ?? $source['total_gain_eur'] ?? self::ORDER_FLOOR,
            2
        );
        $gainYPctOrder = round(
            $source['annualized_percentage_gain'] ?? $source['percentage_gain'] ?? self::ORDER_FLOOR,
            4
        );

        return [$gainYOrder, $gainYPctOrder];
    }

    /**
     * Distinct quadrant and action labels across the overall classification and every period,
     * used by the table filter dropdowns to match a symbol if it appears in ANY horizon.
     *
     * @return array{0: array, 1: array} [quadrantLabels, actionLabels]
     */
    private function _filterLabels(?array $cat): array
    {
        $quadrantLabels = [];
        $actionLabels   = [];

        if ($cat === null) {
            return [$quadrantLabels, $actionLabels];
        }

        $overallLabel = QuadrantClassifier::label($cat['quadrant'] ?? null);
        if ($overallLabel) {
            $quadrantLabels[] = $overallLabel;
        }
        if (!empty($cat['action'])) {
            $actionLabels[] = $cat['action'];
        }

        foreach ($cat['periods'] ?? [] as $periodData) {
            $pLabel = QuadrantClassifier::label($periodData['quadrant'] ?? null);
            if ($pLabel && !in_array($pLabel, $quadrantLabels, true)) {
                $quadrantLabels[] = $pLabel;
            }
            if (!empty($periodData['action']) && !in_array($periodData['action'], $actionLabels, true)) {
                $actionLabels[] = $periodData['action'];
            }
        }

        return [$quadrantLabels, $actionLabels];
    }

    /**
     * Ready-to-display peak-price label per period for the quadrant table's exit-zone tooltips
     * ("From peak", "near peak", "P&L at peak"). Keeps the currency decision in the BE so the blade
     * only echoes the string. Each value is an HTML fragment (carries the &euro; entity), printed
     * with {!! !!}; null when the period has no usable peak.
     *
     * @return array period => string|null   e.g. "471.70 GBX (&euro; 5.46)" or "&euro; 5.46"
     */
    private function _peakLabels(array $quoteData, ?array $cat): array
    {
        $tradeCode = $quoteData['tradeCurrencyModel']->display_code ?? null;
        $tradeIso  = $quoteData['currency'] ?? null;

        $labels = [];
        foreach ($cat['periods'] ?? [] as $period => $periodData) {
            $labels[$period] = $this->_peakLabel(
                $periodData['exit_zone'] ?? null, $tradeCode, $tradeIso
            );
        }

        return $labels;
    }

    /**
     * The symbol's peak in its native trade currency first, then the EUR equivalent in parentheses;
     * EUR-quoted symbols (or those without a native figure) show only the EUR value.
     */
    private function _peakLabel(?array $exitZone, ?string $tradeCode, ?string $tradeIso): ?string
    {
        if ($exitZone === null || ($exitZone['peak_price_eur'] ?? null) === null) {
            return null;
        }

        $eur = MoneyFormat::get_formatted_price_plain($exitZone['peak_price_eur']) . '&euro;';

        if ($tradeIso !== null && $tradeIso !== 'EUR' && $tradeCode !== null
            && ($exitZone['peak_price_native'] ?? null) !== null) {
            return MoneyFormat::get_formatted_price_plain($exitZone['peak_price_native']) . $tradeCode
                . ' (' . $eur . ')';
        }

        return $eur;
    }

    /**
     * Ready-to-display price points behind each window's "Gain", for the quadrant table's gain
     * tooltip. The gain is measured on the EUR price series, so the percentage is reproducible from
     * the EUR figures; each price is also shown in the native trade currency (converted with the
     * same FX rate the peak / current-price labels use) so it mirrors the "From peak" and
     * "P&L at peak" columns.
     *
     * The "latest" point is the symbol's current price as of today (the same label and date the
     * other live columns show), not the last stored close, so all columns agree. raw_pct is the
     * plain start->end move over the window; for the 1Y/2Y rows the badge shows the annualized
     * (CAGR) figure instead, so the tooltip states both. Null per period when no usable price pair.
     *
     * @param string|null $currentPriceLabel current-price label (native + EUR), reused for "latest".
     * @return array period => array{start_label:string,start_date:string,end_label:string,
     *                                end_date:string,raw_pct:float|null,is_annualized:bool}|null
     */
    private function _gainWindows(array $quoteData, ?array $cat, array $liveEurRates, ?string $currentPriceLabel): array
    {
        $tradeIso  = $quoteData['currency'] ?? null;
        $tradeCode = $quoteData['tradeCurrencyModel']->display_code ?? null;
        // native * rate = EUR. Live rate first, else the rate implied by a cached peak (same
        // fallback the current-price label uses), so foreign-currency symbols still get a pair.
        $eurRate   = $liveEurRates[$tradeIso] ?? $this->_peakDerivedEurRate($cat);
        $today     = \Carbon\Carbon::today()->format('d M Y');

        $out = [];
        foreach ($cat['periods'] ?? [] as $period => $periodData) {
            $w     = $periodData['gain_window'] ?? null;
            $start = $w['start_price_eur'] ?? null;
            $end   = $w['end_price_eur'] ?? null;
            if ($w === null || $start === null || $end === null) {
                $out[$period] = null;
                continue;
            }

            $rawPct = ((float) $start > 0.0)
                ? round(((float) $end - (float) $start) / (float) $start * 100.0, 2)
                : null;

            // "Latest" mirrors the live columns: the current price as of today. Fall back to the
            // window's own end point (last stored close) only when there is no current-price label.
            [$endLabel, $endDate] = $currentPriceLabel !== null
                ? [$currentPriceLabel, $today]
                : [
                    $this->_priceLabelFromEur((float) $end, $eurRate, $tradeIso, $tradeCode),
                    \Carbon\Carbon::parse($w['end_date'])->format('d M Y'),
                ];

            $out[$period] = [
                'start_label'   => $this->_priceLabelFromEur((float) $start, $eurRate, $tradeIso, $tradeCode),
                'start_date'    => \Carbon\Carbon::parse($w['start_date'])->format('d M Y'),
                'end_label'     => $endLabel,
                'end_date'      => $endDate,
                'raw_pct'       => $rawPct,
                'is_annualized' => in_array($period, ['1y', '2y'], true),
            ];
        }

        return $out;
    }

    /**
     * Closing-based 52-week range for the "% High" / "% Low" / "52W Range" columns' primary
     * (sortable) figures: the highest and lowest daily CLOSE over the trailing year, in native
     * trade currency, each with its date and its distance from the current price. "% High" is how
     * far the current price sits below the closing high (positive below it); "% Low" is how far it
     * sits above the closing low. Null when no closing extremes are available.
     *
     * @return array{high_native:float|null,high_date:string,high_pct:float|null,
     *                low_native:float|null,low_date:string,low_pct:float|null}|null
     */
    private function _closingRange(array $quoteData, ?array $cat): ?array
    {
        $ext = $cat['closing_extremes'] ?? null;
        if ($ext === null) {
            return null;
        }

        $price = $quoteData['price'] ?? null;
        $price = ($price === null || $price === '') ? null : (float) $price;

        $highNative = $ext['high_native'] ?? null;
        $lowNative  = $ext['low_native'] ?? null;
        $highDate   = $ext['high_date'];
        $lowDate    = $ext['low_date'];

        // Bump extremes with the current live price so that a price that is a new high or low
        // (whether the market is open or the day has just closed) is reflected immediately.
        if ($price !== null && $highNative !== null && $price > (float) $highNative) {
            $highNative = $price;
            $highDate   = \Carbon\Carbon::today()->format('Y-m-d');
        }
        if ($price !== null && $lowNative !== null && $price < (float) $lowNative) {
            $lowNative = $price;
            $lowDate   = \Carbon\Carbon::today()->format('Y-m-d');
        }

        // Distance from the live price: positive when below the closing high / above the closing low.
        $highPct = ($price !== null && $highNative !== null && (float) $highNative > 0.0)
            ? round(((float) $highNative - $price) / (float) $highNative * 100.0, 2)
            : null;
        $lowPct  = ($price !== null && $lowNative !== null && (float) $lowNative > 0.0)
            ? round(($price - (float) $lowNative) / (float) $lowNative * 100.0, 2)
            : null;

        return [
            'high_native' => $highNative,
            'high_date'   => \Carbon\Carbon::parse($highDate)->format('d M Y'),
            'high_pct'    => $highPct,
            'low_native'  => $lowNative,
            'low_date'    => \Carbon\Carbon::parse($lowDate)->format('d M Y'),
            'low_pct'     => $lowPct,
        ];
    }

    /**
     * Format an EUR price the same way as the peak / current-price labels: native trade currency
     * first with the EUR equivalent in parentheses, or EUR only for EUR-quoted symbols (or when no
     * FX rate is available). The native value is the EUR price divided by the same rate the other
     * labels convert with, so historical points share the peak/current conversion.
     */
    private function _priceLabelFromEur(float $eur, ?float $eurRate, ?string $tradeIso, ?string $tradeCode): string
    {
        $eurStr = MoneyFormat::get_formatted_price_plain($eur) . '&euro;';

        if ($tradeIso !== null && $tradeIso !== 'EUR' && $tradeCode !== null
            && $eurRate !== null && (float) $eurRate > 0.0) {
            $native = $eur / (float) $eurRate;
            return MoneyFormat::get_formatted_price_plain($native) . $tradeCode . ' (' . $eurStr . ')';
        }

        return $eurStr;
    }

    /**
     * The symbol's current price, formatted the same way as the peak label (native trade currency
     * first, then the EUR equivalent in parentheses; EUR-quoted symbols show only the EUR value).
     * Shown above the peak in the "From peak" tooltip so the proximity percentage is traceable.
     *
     * @return string|null HTML fragment (carries the &euro; entity), or null when no usable price.
     */
    private function _currentPriceLabel(array $quoteData, array $liveEurRates, ?array $cat): ?string
    {
        $native = $quoteData['price'] ?? null;
        if ($native === null || $native === '' || (float) $native <= 0.0) {
            return null;
        }
        $native = (float) $native;

        $tradeIso  = $quoteData['currency'] ?? null;
        $tradeCode = $quoteData['tradeCurrencyModel']->display_code ?? null;

        if ($tradeIso === 'EUR' || $tradeIso === null) {
            return MoneyFormat::get_formatted_price_plain($native) . '&euro;';
        }

        // Prefer the live FX rate; fall back to the rate implied by a cached window peak so the
        // current price still carries a EUR figure (and shares the peak's conversion) when no live
        // rate is available, e.g. a foreign-currency watchlist symbol with no held position.
        $eurRate = $liveEurRates[$tradeIso] ?? $this->_peakDerivedEurRate($cat);
        if ($eurRate === null || $tradeCode === null) {
            return $tradeCode !== null
                ? MoneyFormat::get_formatted_price_plain($native) . $tradeCode
                : MoneyFormat::get_formatted_price_plain($native) . '&euro;';
        }

        $eur = MoneyFormat::get_formatted_price_plain($native * (float) $eurRate) . '&euro;';

        return MoneyFormat::get_formatted_price_plain($native) . $tradeCode . ' (' . $eur . ')';
    }

    /**
     * Native -> EUR rate implied by a cached window peak (peak EUR / peak native). Used only as a
     * fallback for the current-price label when no live FX rate is available, so the label converts
     * with the same rate the peak label already shows. Null when no usable peak exists.
     */
    private function _peakDerivedEurRate(?array $cat): ?float
    {
        foreach ($cat['periods'] ?? [] as $periodData) {
            $zone   = $periodData['exit_zone'] ?? null;
            $eur    = $zone['peak_price_eur'] ?? null;
            $native = $zone['peak_price_native'] ?? null;
            if ($eur !== null && $native !== null && (float) $native > 0.0) {
                return (float) $eur / (float) $native;
            }
        }

        return null;
    }

    /**
     * Whether the symbol is within the "near peak" range in at least one quadrant window
     * (3M / 6M / 1Y / 2Y), used by the table's "Near peak" filter. Mirrors the per-window test the
     * quadrant table renders in tier-quadrant-perf-row.blade.php (proximity_pct within the same
     * per-window threshold that fires a peak-proximity email); keep the two in sync.
     */
    private function _hasNearPeak(?array $cat): bool
    {
        if ($cat === null) {
            return false;
        }

        foreach ($cat['periods'] ?? [] as $period => $periodData) {
            $proximityPct = $periodData['exit_zone']['proximity_pct'] ?? null;
            if ($proximityPct === null) {
                continue;
            }

            $threshold = config("alerts.peak_proximity.window_thresholds.{$period}")
                ?? config('alerts.peak_proximity.threshold_pct', 5);
            if ((float) $proximityPct >= -(float) $threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * EUR gain figure that matches whatever decided the tier, shown on the tier line.
     * Annualized gain for an annualized basis, total gain for a raw-return basis, null otherwise.
     */
    private function _basisGainEur(?array $cat, array $perf): ?float
    {
        if ($cat === null || empty($perf['has_data'])) {
            return null;
        }

        $basis = $cat['basis'] ?? null;
        if ($basis === TierDecision::BASIS_ANNUALIZED_RETURN) {
            return $perf['annualized_gain_eur'] ?? null;
        }
        if ($basis === TierDecision::BASIS_RAW_RETURN) {
            return $perf['total_gain_eur'] ?? null;
        }

        return null;
    }
}

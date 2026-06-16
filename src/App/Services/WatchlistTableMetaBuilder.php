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
    public function attach(array $items): array
    {
        foreach ($items as $symbol => $quoteData) {
            $perf = $quoteData['performance'] ?? [];
            $cat  = $quoteData['categorization'] ?? null;

            [$gainYOrder, $gainYPctOrder] = $this->_gainYOrder($perf);
            [$quadrantLabels, $actionLabels] = $this->_filterLabels($cat);

            $items[$symbol]['table_meta'] = [
                'tier_text'        => TierCalculationService::tierLabel($cat['effective_tier'] ?? null) ?? '',
                'quadrant_labels'  => $quadrantLabels,
                'action_labels'    => $actionLabels,
                'gain_y_order'     => $gainYOrder,
                'gain_y_pct_order' => $gainYPctOrder,
                'basis_gain_eur'   => $this->_basisGainEur($cat, $perf),
                'peak_labels'      => $this->_peakLabels($quoteData, $cat),
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

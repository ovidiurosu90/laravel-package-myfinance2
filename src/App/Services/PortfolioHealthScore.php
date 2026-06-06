<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

class PortfolioHealthScore
{
    private const PLATINUM_GOLD_CAUTION_THRESHOLD = 60.0;
    private const SILVER_CAUTION_THRESHOLD        = 25.0;
    private const BRONZE_RUST_WARNING_THRESHOLD   = 15.0;

    /**
     * Build the full health-score view model from the dashboard categorization and the
     * positions overview.
     *
     * Resolves the per-tier totals (compute), enriches each owned symbol with its display
     * detail row, then groups and sorts those rows into the buckets the card renders. Returns
     * null when there are no owned positions to evaluate.
     *
     * @param array $categorization      symbol => categorization array (tier, quadrant, ...)
     * @param array $allGroupedPositions account => open_positions[] (from the positions overview)
     * @param array $items               symbol => dashboard quoteData (performance, categorization)
     */
    public function build(array $categorization, array $allGroupedPositions, array $items = []): ?array
    {
        $tierBySymbol          = [];
        $openPositionsBySymbol = [];

        foreach ($allGroupedPositions as $positions) {
            foreach ($positions as $position) {
                $symbol = $position['symbol'];
                $tierBySymbol[$symbol]            = $categorization[$symbol]['effective_tier'] ?? null;
                $openPositionsBySymbol[$symbol][] = $position;
            }
        }

        if (empty($openPositionsBySymbol)) {
            return null;
        }

        // Convert USD with the same latest EURUSD the positions overview chart shows
        // (last point of the EURUSD=X series), so the card totals reconcile with /positions.
        $eurRates = ['EUR' => 1.0];
        $eurUsd   = ChartsBuilder::getLatestSymbolValue('EURUSD=X');
        if ($eurUsd) {
            $eurRates['USD'] = 1.0 / $eurUsd;
        }

        $healthScore = $this->compute($tierBySymbol, $openPositionsBySymbol, $eurRates);

        // Unlisted FMV symbols are excluded from the Yahoo Finance $items dict but are
        // present in openPositionsBySymbol. Inject their categorization so _buildSymbolDetails
        // can read the correct basis and render the bold indicator in the Gain/y column.
        foreach (array_keys($tierBySymbol) as $sym) {
            if (!array_key_exists('categorization', $items[$sym] ?? []) && isset($categorization[$sym])) {
                $items[$sym]['categorization'] = $categorization[$sym];
            }
        }

        $symbolDetails = $this->_buildSymbolDetails(
            $tierBySymbol, $openPositionsBySymbol, $items, $eurRates
        );

        $totalMvalue = $healthScore['total_mvalue_eur'];
        $totalCost   = $healthScore['total_cost_eur'];

        $platinumGoldSymbols = [];
        $silverSymbols       = [];
        $bronzeRustSymbols   = [];
        $unratedSymbols      = [];

        foreach ($symbolDetails as $detail) {
            $detail['mvalue_pct'] = $totalMvalue > 0
                ? round($detail['mvalue_eur'] / $totalMvalue * 100.0, 1) : 0.0;
            $detail['cost_pct'] = $totalCost > 0
                ? round($detail['cost_eur'] / $totalCost * 100.0, 1) : 0.0;

            $tier = $detail['tier'];
            if ($tier === null) {
                // Unrated: no usable return data. Shown in the left table for visibility,
                // not silently grouped with Bronze/Rust.
                $unratedSymbols[] = $detail;
            } elseif (in_array($tier, [TierCalculationService::PLATINUM, TierCalculationService::GOLD])) {
                $platinumGoldSymbols[] = $detail;
            } elseif ($tier === TierCalculationService::SILVER) {
                $silverSymbols[] = $detail;
            } else {
                $bronzeRustSymbols[] = $detail;
            }
        }

        $sortByMvalue = fn ($a, $b) => $b['mvalue_eur'] <=> $a['mvalue_eur'];
        usort($platinumGoldSymbols, $sortByMvalue);
        usort($silverSymbols, $sortByMvalue);
        usort($bronzeRustSymbols, $sortByMvalue);
        usort($unratedSymbols, $sortByMvalue);

        $healthScore['platinum_gold_symbols'] = $platinumGoldSymbols;
        $healthScore['silver_symbols']        = $silverSymbols;
        $healthScore['bronze_rust_symbols']   = $bronzeRustSymbols;
        $healthScore['unrated_symbols']        = $unratedSymbols;

        return $healthScore;
    }

    /**
     * Compute portfolio health from owned positions.
     *
     * @param array $tierBySymbol           symbol => effective_tier string (or null)
     * @param array $openPositionsBySymbol  symbol => open_positions array (from positions overview)
     * @param array $eurRates               currency iso code => account-currency to EUR multiplier
     */
    public function compute(array $tierBySymbol, array $openPositionsBySymbol, array $eurRates = ['EUR' => 1.0]): array
    {
        $empty = [
            TierCalculationService::PLATINUM => 0.0,
            TierCalculationService::GOLD     => 0.0,
            TierCalculationService::SILVER   => 0.0,
            TierCalculationService::BRONZE   => 0.0,
            TierCalculationService::RUST     => 0.0,
        ];

        $groupMvalue = $empty;
        $groupCost   = $empty;
        $totalMvalue = 0.0;
        $totalCost   = 0.0;

        foreach ($tierBySymbol as $symbol => $tier) {
            // Unrated positions (no usable return data) are excluded entirely: they
            // are not counted toward the totals or any tier bucket, so they cannot
            // be silently misrepresented as a positive (Bronze) allocation.
            if ($tier === null) {
                continue;
            }

            $positions = $openPositionsBySymbol[$symbol] ?? [];
            if (empty($positions)) {
                continue;
            }

            $mvalue = 0.0;
            $cost   = 0.0;
            foreach ($positions as $position) {
                $currency = $position['accountModel']->currency->iso_code ?? 'EUR';
                $rate     = (float) ($eurRates[$currency] ?? 1.0);
                $mvalue  += (float) ($position['market_value_in_account_currency'] ?? 0.0) * $rate;
                $cost    += (float) ($position['cost2_in_account_currency'] ?? 0.0) * $rate;
            }

            $totalMvalue += $mvalue;
            $totalCost   += $cost;

            $groupMvalue[$tier] += $mvalue;
            $groupCost[$tier]   += $cost;
        }

        $metrics      = ChartsBuilder::getAccountMetrics();
        $mvalueColor  = $metrics['mvalue']['line_color'] ?? 'inherit';
        $costColor    = $metrics['cost']['line_color']   ?? 'inherit';

        if ($totalMvalue <= 0.0) {
            return [
                'total_mvalue_eur'              => 0.0,
                'total_cost_eur'                => 0.0,
                'platinum_gold_mvalue_eur'      => 0.0,
                'silver_mvalue_eur'             => 0.0,
                'bronze_rust_mvalue_eur'        => 0.0,
                'platinum_gold_cost_eur'        => 0.0,
                'silver_cost_eur'               => 0.0,
                'bronze_rust_cost_eur'          => 0.0,
                'platinum_gold_pct'             => 0.0,
                'silver_pct'                    => 0.0,
                'bronze_rust_pct'               => 0.0,
                'platinum_gold_cost_pct'        => 0.0,
                'silver_cost_pct'               => 0.0,
                'bronze_rust_cost_pct'          => 0.0,
                'platinum_gold_target'          => self::PLATINUM_GOLD_CAUTION_THRESHOLD,
                'silver_target'                 => self::SILVER_CAUTION_THRESHOLD,
                'bronze_rust_target'            => self::BRONZE_RUST_WARNING_THRESHOLD,
                'platinum_gold_target_eur'      => 0.0,
                'silver_target_eur'             => 0.0,
                'bronze_rust_target_eur'        => 0.0,
                'platinum_gold_cost_target_eur' => 0.0,
                'silver_cost_target_eur'        => 0.0,
                'bronze_rust_cost_target_eur'   => 0.0,
                'mvalue_color'                  => $mvalueColor,
                'cost_color'                    => $costColor,
                'signal'                        => 'healthy',
                'signal_message'                => 'No owned positions to evaluate.',
            ];
        }

        $platinumGoldMvalue = $groupMvalue[TierCalculationService::PLATINUM]
            + $groupMvalue[TierCalculationService::GOLD];
        $silverMvalue       = $groupMvalue[TierCalculationService::SILVER];
        $bronzeRustMvalue   = $groupMvalue[TierCalculationService::BRONZE]
            + $groupMvalue[TierCalculationService::RUST];
        $platinumGoldCost   = $groupCost[TierCalculationService::PLATINUM]
            + $groupCost[TierCalculationService::GOLD];
        $silverCost         = $groupCost[TierCalculationService::SILVER];
        $bronzeRustCost     = $groupCost[TierCalculationService::BRONZE]
            + $groupCost[TierCalculationService::RUST];

        $platinumGoldPct     = $platinumGoldMvalue / $totalMvalue * 100.0;
        $silverPct           = $silverMvalue       / $totalMvalue * 100.0;
        $bronzeRustPct       = $bronzeRustMvalue   / $totalMvalue * 100.0;
        $platinumGoldCostPct = $totalCost > 0.0 ? $platinumGoldCost / $totalCost * 100.0 : 0.0;
        $silverCostPct       = $totalCost > 0.0 ? $silverCost       / $totalCost * 100.0 : 0.0;
        $bronzeRustCostPct   = $totalCost > 0.0 ? $bronzeRustCost   / $totalCost * 100.0 : 0.0;

        [$signal, $message] = $this->_resolveSignal($platinumGoldPct, $bronzeRustPct);

        return [
            'total_mvalue_eur'              => round($totalMvalue, 2),
            'total_cost_eur'                => round($totalCost, 2),
            'platinum_gold_mvalue_eur'      => round($platinumGoldMvalue, 2),
            'silver_mvalue_eur'             => round($silverMvalue, 2),
            'bronze_rust_mvalue_eur'        => round($bronzeRustMvalue, 2),
            'platinum_gold_cost_eur'        => round($platinumGoldCost, 2),
            'silver_cost_eur'               => round($silverCost, 2),
            'bronze_rust_cost_eur'          => round($bronzeRustCost, 2),
            'platinum_gold_pct'             => round($platinumGoldPct, 1),
            'silver_pct'                    => round($silverPct, 1),
            'bronze_rust_pct'               => round($bronzeRustPct, 1),
            'platinum_gold_cost_pct'        => round($platinumGoldCostPct, 1),
            'silver_cost_pct'               => round($silverCostPct, 1),
            'bronze_rust_cost_pct'          => round($bronzeRustCostPct, 1),
            'platinum_gold_target'          => self::PLATINUM_GOLD_CAUTION_THRESHOLD,
            'silver_target'                 => self::SILVER_CAUTION_THRESHOLD,
            'bronze_rust_target'            => self::BRONZE_RUST_WARNING_THRESHOLD,
            'platinum_gold_target_eur'      => round($totalMvalue * self::PLATINUM_GOLD_CAUTION_THRESHOLD / 100.0, 2),
            'silver_target_eur'             => round($totalMvalue * self::SILVER_CAUTION_THRESHOLD / 100.0, 2),
            'bronze_rust_target_eur'        => round($totalMvalue * self::BRONZE_RUST_WARNING_THRESHOLD / 100.0, 2),
            'platinum_gold_cost_target_eur' => round($totalCost * self::PLATINUM_GOLD_CAUTION_THRESHOLD / 100.0, 2),
            'silver_cost_target_eur'        => round($totalCost * self::SILVER_CAUTION_THRESHOLD / 100.0, 2),
            'bronze_rust_cost_target_eur'   => round($totalCost * self::BRONZE_RUST_WARNING_THRESHOLD / 100.0, 2),
            'mvalue_color'                  => $mvalueColor,
            'cost_color'                    => $costColor,
            'signal'                        => $signal,
            'signal_message'                => $message,
        ];
    }

    private function _resolveSignal(float $platinumGoldPct, float $bronzeRustPct): array
    {
        if ($bronzeRustPct >= self::BRONZE_RUST_WARNING_THRESHOLD) {
            return ['warning', 'Portfolio is weighed down.'];
        }
        if ($platinumGoldPct < self::PLATINUM_GOLD_CAUTION_THRESHOLD) {
            return ['caution', 'Strong core below target. Add to best positions.'];
        }
        return ['healthy', 'Portfolio core is healthy.'];
    }

    private function _buildSymbolDetails(
        array $tierBySymbol,
        array $openPositionsBySymbol,
        array $items,
        array $eurRates
    ): array
    {
        $details = [];

        foreach ($tierBySymbol as $symbol => $tier) {
            $positions = $openPositionsBySymbol[$symbol] ?? [];
            if (empty($positions)) {
                continue;
            }

            $mvalue = 0.0;
            $cost   = 0.0;
            foreach ($positions as $position) {
                $currency = $position['accountModel']->currency->iso_code ?? 'EUR';
                $rate     = (float) ($eurRates[$currency] ?? 1.0);
                $mvalue  += (float) ($position['market_value_in_account_currency'] ?? 0.0) * $rate;
                $cost    += (float) ($position['cost2_in_account_currency'] ?? 0.0) * $rate;
            }

            $perf       = $items[$symbol]['performance'] ?? [];
            $cat        = $items[$symbol]['categorization'] ?? null;
            $cat        = is_array($cat) ? $cat : [];
            $openWin    = null;
            $openInvest = 0.0;
            $openGain   = 0.0;
            if (!empty($perf['has_data'])) {
                foreach ($perf['windows'] ?? [] as $w) {
                    if (empty($w['is_open'])) {
                        continue;
                    }
                    if ($openWin === null) {
                        $openWin = $w;
                    }
                    $openInvest += (float) ($w['invested_eur'] ?? 0.0);
                    $openGain   += (float) ($w['total_gain_eur'] ?? 0.0);
                }
            }

            // "Current" raw gain is the open holding window(s) total gain (matches the quadrant
            // "Owned Now" figure). Falls back to mvalue minus cost only when no open window exists.
            if ($openInvest > 0.0) {
                $rawGainEur = round($openGain, 2);
                $rawGainPct = round($openGain / $openInvest * 100.0, 2);
            } else {
                $rawGainEur = round($mvalue - $cost, 2);
                $rawGainPct = $cost > 0.0 ? round(($mvalue - $cost) / $cost * 100.0, 2) : null;
            }

            $overallAnnEur = isset($perf['annualized_gain_eur']) && $perf['annualized_gain_eur'] !== null
                ? round((float) $perf['annualized_gain_eur'], 2) : null;

            $currentAnnPct = $openWin ? ($openWin['annualized_percentage_gain'] ?? null) : null;
            $currentAnnEur = $openWin && isset($openWin['annualized_gain_eur'])
                ? round((float) $openWin['annualized_gain_eur'], 2) : null;
            $overallAnnPct = $perf['annualized_percentage_gain'] ?? null;
            $windowCount   = (int) ($perf['window_count'] ?? 1);

            // For owned symbols with no performance-service return (unlisted/FMV holdings), the
            // tier was decided from the position return. When that basis is annualized, surface the
            // same figure here so the bold deciding line matches the tier instead of showing raw.
            if ($overallAnnPct === null
                && ($cat['basis'] ?? null) === TierDecision::BASIS_ANNUALIZED_RETURN
                && ($cat['basis_value'] ?? null) !== null
            ) {
                $overallAnnPct = round((float) $cat['basis_value'], 2);
                $overallAnnEur = $cost > 0.0 ? round($cost * $overallAnnPct / 100.0, 2) : null;
            }

            // For unlisted FMV symbols the SymbolPerformanceService cache has unrealized = 0
            // (no StatHistorical price for unlisted symbols), so the cached annualized reflects
            // a zero unrealized gain rather than the live FMV value. Compute the CAGR from the
            // live mvalue-cost position data and apply it to both the current and overall rows so
            // both lines show the real annualized return and the overall line is clearly bold.
            if (FinanceAPI::isUnlisted($symbol) && $rawGainPct !== null) {
                $holdDays = $this->_resolvePositionDays($openWin, $positions);
                if ($holdDays >= 365) {
                    $liveAnnPct    = (pow(1.0 + $rawGainPct / 100.0, 365.0 / $holdDays) - 1.0) * 100.0;
                    $overallAnnPct = round($liveAnnPct, 2);
                    $overallAnnEur = $cost > 0.0 ? round($cost * $overallAnnPct / 100.0, 2) : null;
                    $currentAnnPct = $overallAnnPct;
                    $currentAnnEur = $overallAnnEur;
                }
            }

            // Surface the overall line for every symbol with any gain data, including unlisted
            // symbols that have no perf windows but do have a mvalue-cost figure.
            $showOverall = !empty($perf['has_data']) || $rawGainEur !== null;

            // Always show the current open line alongside the overall line. For a single window
            // the two carry the same figure, but both rows are kept so every symbol has the same
            // current / overall / market layout.
            $showCurrent = $showOverall;

            // Overall cost = total capital deployed across all windows; overall mvalue = that plus
            // total gain (realized + unrealized + dividends), i.e. the lifetime ending value.
            $overallCostEur   = isset($perf['total_invested_eur'])
                ? round((float) $perf['total_invested_eur'], 2) : null;
            $overallMvalueEur = ($overallCostEur !== null && isset($perf['total_gain_eur']))
                ? round($overallCostEur + (float) $perf['total_gain_eur'], 2) : null;

            $details[$symbol] = [
                'symbol'               => $symbol,
                'tier'                 => $tier,
                'cost_eur'             => round($cost, 2),
                'mvalue_eur'           => round($mvalue, 2),
                'annualized_gain_eur'  => $currentAnnEur,
                'annualized_pct'       => $currentAnnPct,
                'raw_gain_eur'         => $rawGainEur,
                'raw_gain_pct'         => $rawGainPct,
                'overall_ann_eur'      => $overallAnnEur,
                'overall_ann_pct'      => $overallAnnPct,
                'overall_raw_eur'      => isset($perf['total_gain_eur'])
                    ? round((float) $perf['total_gain_eur'], 2) : $rawGainEur,
                'overall_raw_pct'      => $perf['percentage_gain'] ?? $rawGainPct,
                'overall_cost_eur'     => $overallCostEur,
                'overall_mvalue_eur'   => $overallMvalueEur,
                'overall_period'       => $perf['holding_period_display'] ?? null,
                'window_count'         => $windowCount,
                'show_overall'         => $showOverall,
                'show_current'         => $showCurrent,
                'market_1y_pct'        => $cat['candidates']['market_1y_pct']
                    ?? ($cat['momenta']['1y'] ?? null),
                // Money-weighted return and the same-window S&P 500 (VUSA.AS) comparison,
                // shown next to the CAGR figures so all three "per year" views agree.
                'xirr_pct'             => $cat['xirr_pct'] ?? ($perf['xirr_pct'] ?? null),
                'total_days'           => (int) ($perf['total_days'] ?? 0),
                'alpha_vs_vusa_pct'    => $cat['alpha_vs_vusa_pct'] ?? null,
                'alpha_is_short_period' => $cat['alpha_is_short_period'] ?? false,
                'vusa_same_window_pct' => $cat['vusa_same_window_pct'] ?? null,
                'vusa_same_window_raw_pct' => $cat['vusa_same_window_raw_pct'] ?? null,
                'basis'                => $cat['basis'] ?? TierDecision::BASIS_NONE,
                'basis_label'          => $cat['basis_label']
                    ?? TierDecision::basisLabel(TierDecision::BASIS_NONE),
                'basis_value'          => $cat['basis_value'] ?? null,
                'confidence'           => $cat['confidence'] ?? TierDecision::CONFIDENCE_HIGH,
                'has_override'         => $cat['has_override'] ?? false,
                'is_stale'             => $cat['is_stale'] ?? false,
                'explanation'          => $cat['explanation'] ?? '',
                'position_start'       => $this->_resolvePositionStart($openWin, $positions),
                'position_days'        => $this->_resolvePositionDays($openWin, $positions),
            ];
        }

        return $details;
    }

    private function _resolvePositionStart(?array $openWin, array $positions): mixed
    {
        if ($openWin !== null) {
            return $openWin['start_date'] ?? null;
        }
        $earliest = null;
        foreach ($positions as $position) {
            foreach ($position['trades'] ?? [] as $trade) {
                if ($earliest === null || $trade->timestamp < $earliest) {
                    $earliest = $trade->timestamp;
                }
            }
        }
        return $earliest;
    }

    private function _resolvePositionDays(?array $openWin, array $positions): int
    {
        if ($openWin !== null) {
            return (int) ($openWin['duration_days'] ?? 0);
        }
        $start = $this->_resolvePositionStart(null, $positions);
        return $start !== null ? (int) $start->diffInDays(now()) : 0;
    }
}

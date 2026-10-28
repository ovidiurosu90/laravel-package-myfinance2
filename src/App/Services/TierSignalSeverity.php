<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * Severity and caution flags of a tier signal chip (TierSignalChip). Never a gate: the chip shows either way, these
 * only say how strongly the price and the position back it.
 *
 *   SELL / TRIM   High when the position is size_high_pct (5) % of the portfolio or more, RSI is overbought (70), or
 *                 a 6M / 1Y / 2Y window is near its peak (the peak-proximity thresholds); else Medium for SELL, Low
 *                 for TRIM.
 *   BUY / ADD     Medium, with caution flags: price under MA200, quadrant Danger zone, the position already
 *                 max_position_pct (10) % of the portfolio or more, the 1Y market figure rejected as an artifact.
 *   REVIEW        Medium.
 *   no action     (a watch-only rare high or deterioration) no severity.
 *
 * Runs on the dashboard after the technical indicators and the health card (position sizes): attach(). The rules in
 * apply() are pure.
 */
final class TierSignalSeverity
{
    public const HIGH   = 'HIGH';
    public const MEDIUM = 'MEDIUM';
    public const LOW    = 'LOW';

    public function __construct(
        private readonly float $_sizeHighPct = 5.0,
        private readonly float $_maxPositionPct = 10.0,
        private readonly float $_rsiOverbought = 70.0,
        private readonly array $_meaningfulWindows = ['6m', '1y', '2y'],
        private readonly array $_windowThresholds = []
    )
    {
    }

    public static function fromConfig(): self
    {
        return new self(
            (float) config('myfinance2.watchlistsymbols.tier_history.size_high_pct', 5),
            (float) config('myfinance2.watchlistsymbols.tier_history.max_position_pct', 10),
            (float) config('myfinance2.alerts.peak_proximity.rsi_overbought', 70),
            (array) config('myfinance2.alerts.peak_proximity.meaningful_windows', ['6m', '1y', '2y']),
            (new PeakProximityAlertService())->windowThresholds(null)
        );
    }

    /**
     * Adds severity and flags to each item's chip (not to a deferring one, which shows no chip).
     *
     * @param array      $items       symbol => quoteData, with categorization and technical_indicators attached
     * @param array|null $healthScore PortfolioHealthScore::build(): the per-symbol rows carry mvalue_pct
     */
    public function attach(array $items, ?array $healthScore): array
    {
        $positionPct = [];
        foreach (['platinum_gold_symbols', 'silver_symbols', 'bronze_rust_symbols', 'unrated_symbols'] as $group) {
            foreach ($healthScore[$group] ?? [] as $row) {
                $positionPct[$row['symbol']] = isset($row['mvalue_pct']) ? (float) $row['mvalue_pct'] : null;
            }
        }

        foreach ($items as $symbol => $quoteData) {
            $chip = $quoteData['categorization']['tier_signal_chip'] ?? null;
            if ($chip === null || $chip['deferred_to'] !== null) {
                continue;
            }
            $items[$symbol]['categorization']['tier_signal_chip'] = $this->apply(
                $chip, $this->_context($quoteData, $positionPct[$symbol] ?? null)
            );
        }

        return $items;
    }

    /**
     * @param array $context position_pct (?float), rsi (?float), below_ma200 (bool), near_peak (string[] window
     *                       labels), danger_zone (bool), market_artifact (bool)
     */
    public function apply(array $chip, array $context): array
    {
        $size = $context['position_pct'] ?? null;
        $rsi  = $context['rsi'] ?? null;

        [$severity, $reasons, $flags] = match ($chip['action']) {
            SettledTierResolver::ACTION_SELL, SettledTierResolver::ACTION_TRIM => $this->_sellSeverity(
                $chip['action'], $size, $rsi, $context['near_peak'] ?? []
            ),
            SettledTierResolver::ACTION_BUY, SettledTierResolver::ACTION_ADD => [
                self::MEDIUM, [], $this->_buyFlags($size, $context),
            ],
            SettledTierResolver::ACTION_REVIEW => [self::MEDIUM, [], []],
            default                            => [null, [], []],
        };

        $chip['severity'] = $severity;
        $chip['reasons']  = $reasons;
        $chip['flags']    = $flags;
        $chip['title']    = TierSignalChip::title($chip);

        return $chip;
    }

    /**
     * @return array{0: string, 1: string[], 2: string[]}
     */
    private function _sellSeverity(string $action, ?float $size, ?float $rsi, array $nearPeak): array
    {
        $reasons = [];
        if ($size !== null && $size >= $this->_sizeHighPct) {
            $reasons[] = 'position ' . $this->_pct($size, 1) . ' of the portfolio';
        }
        if ($rsi !== null && $rsi >= $this->_rsiOverbought) {
            $reasons[] = 'RSI ' . MoneyFormat::get_formatted_number_plain($rsi, 0) . ', overbought';
        }
        if (!empty($nearPeak)) {
            $reasons[] = implode(', ', array_map('strtoupper', $nearPeak)) . ' near peak';
        }
        if (!empty($reasons)) {
            return [self::HIGH, $reasons, []];
        }

        return [$action === SettledTierResolver::ACTION_SELL ? self::MEDIUM : self::LOW, [], []];
    }

    /**
     * @return string[]
     */
    private function _buyFlags(?float $size, array $context): array
    {
        $flags = [];
        if (!empty($context['below_ma200'])) {
            $flags[] = 'price under MA200';
        }
        if (!empty($context['danger_zone'])) {
            $flags[] = 'quadrant Danger zone';
        }
        if ($size !== null && $size >= $this->_maxPositionPct) {
            $flags[] = 'position already ' . $this->_pct($size, 1) . ' of the portfolio';
        }
        if (!empty($context['market_artifact'])) {
            $flags[] = 'the 1Y market figure was rejected as an artifact';
        }

        return $flags;
    }

    private function _context(array $quoteData, ?float $positionPct): array
    {
        $cat  = $quoteData['categorization'] ?? [];
        $tech = $quoteData['technical_indicators'] ?? [];

        $nearPeak = [];
        foreach ($this->_meaningfulWindows as $window) {
            $proximity = $cat['exit_zones'][$window]['proximity_pct'] ?? null;
            $threshold = $this->_windowThresholds[$window] ?? null;
            if ($proximity !== null && $threshold !== null && (float) $proximity >= -(float) $threshold) {
                $nearPeak[] = $window;
            }
        }

        return [
            'position_pct'    => !empty($quoteData['open_positions']) ? $positionPct : null,
            'rsi'             => isset($tech['rsi']) ? (float) $tech['rsi'] : null,
            'below_ma200'     => isset($tech['ma200_diff_pct']) && (float) $tech['ma200_diff_pct'] < 0,
            'near_peak'       => $nearPeak,
            'danger_zone'     => ($cat['quadrant'] ?? null) === QuadrantClassifier::DANGER_ZONE,
            'market_artifact' => !empty($cat['market_artifact']),
        ];
    }

    private function _pct(float $value, int $decimals): string
    {
        return MoneyFormat::get_formatted_number_plain($value, $decimals) . "\u{00A0}%";
    }
}

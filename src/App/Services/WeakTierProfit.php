<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * The weak-tier-in-profit signal: an open position in profit on a stock whose tier in use is weak (Rust or Bronze
 * by default). The trend gives little reason to keep the gain at risk, so it reads TAKE PROFIT: sell all or part.
 *
 * A symbol qualifies when all of these hold:
 *   - you hold it (an open position with a known gain);
 *   - it is not the benchmark and not range-bound (RangeBoundPattern reads those on their swing: TRIM near the
 *     high), so one position gets one message;
 *   - its effective tier (manual override, else settled, else today's) is one of the configured tiers;
 *   - the open position is up more than min_gain_pct (default 0: any profit).
 *
 * Backtest note (phase 4, TierSignalBacktest + PositionReadingAsOf, 2026-10-13; revisit with more data): on one
 * portfolio's stored history (2025-03 to 2026-10; the open gain read as the day's holding-period return, the tier as
 * the backtest's settled tier), a TAKE PROFIT reading was followed by trailing VUSA.AS over 126 trading days in 60 %
 * of 57 episodes against a 56 % base rate, median -4.7 points against -3.3 (63 % of 54, -5.8 before the drop-rule
 * fix): modest support, so the alert stays as is.
 * To tune it on a rerun: min_gain_pct or the tiers (config myfinance2.watchlistsymbols.weak_tier_profit).
 *
 * Pure: no database, no config read outside fromConfig().
 */
final class WeakTierProfit
{
    public const LABEL  = 'Weak tier in profit';
    public const ACTION = 'TAKE PROFIT';

    private const NBSP = "\u{00A0}";

    /**
     * @param string[] $_tiers TierCalculationService constants that count as weak
     */
    public function __construct(
        private readonly array $_tiers = [TierCalculationService::RUST, TierCalculationService::BRONZE],
        private readonly float $_minGainPct = 0.0
    )
    {
    }

    public static function fromConfig(): self
    {
        $cfg = (array) config('myfinance2.watchlistsymbols.weak_tier_profit', []);

        return new self(
            array_map('strtoupper', (array) ($cfg['tiers'] ?? [TierCalculationService::RUST,
                TierCalculationService::BRONZE])),
            (float) ($cfg['min_gain_pct'] ?? 0.0)
        );
    }

    /**
     * The weak-tier-in-profit reading of one symbol, or null when it does not hold.
     *
     * @param array      $cat     CategorizationService entry: effective_tier, settled_tier, tier_signal (status),
     *                            has_override, is_benchmark, range_bound
     * @param float|null $gainPct the open position's unrealized gain (%), null when not held
     * @param float|null $gainEur the same gain in EUR, when known
     *
     * @return array{label: string, action: string, title: string, tier: string, tier_source: string,
     *     gain_pct: float, gain_eur: ?float}|null
     */
    public function detect(array $cat, ?float $gainPct, ?float $gainEur = null): ?array
    {
        $tier = strtoupper((string) ($cat['effective_tier'] ?? ''));
        if (!empty($cat['is_benchmark']) || !empty($cat['range_bound']) || $gainPct === null
            || $gainPct <= $this->_minGainPct || !in_array($tier, $this->_tiers, true)
        ) {
            return null;
        }

        $reading = [
            'label'       => self::LABEL,
            'action'      => self::ACTION,
            'tier'        => $tier,
            'tier_source' => match (true) {
                !empty($cat['has_override']) => 'your manual tier',
                // A lasting drop makes the settled tier follow today (SettledTierResolver), so it is not a median.
                ($cat['tier_signal']['status'] ?? null) === SettledTierResolver::STATUS_DETERIORATING
                    => 'following today after a lasting drop',
                ($cat['settled_tier'] ?? null) !== null => 'settled over the last 12 months',
                default => "today's reading",
            },
            'gain_pct'    => round($gainPct, 2),
            'gain_eur'    => $gainEur !== null ? round($gainEur, 2) : null,
        ];
        $reading['title'] = self::title($reading);

        return $reading;
    }

    /**
     * The explanation shown in every tooltip and in the alert email.
     */
    public static function title(array $reading): string
    {
        return self::LABEL . ': the tier in use is ' . TierCalculationService::tierLabel($reading['tier']) . ' ('
            . $reading['tier_source'] . ', a weak trend) while your open position is up '
            . TierApproachComparison::formatShort((float) $reading['gain_pct'], '%')
            . '. A weak trend gives little reason to keep the gain at risk: ' . self::ACTION . ', sell all or part.';
    }

    /**
     * "up + 71.4 % on the open position", for the alert email's subject and header and the inbox card.
     */
    public static function gainText(array $reading): string
    {
        return 'up ' . TierApproachComparison::formatShort((float) $reading['gain_pct'], '%') . ' on the open'
            . self::NBSP . 'position';
    }

    /**
     * The open position's unrealized gain from the performance windows (EUR, last close or live price): the sum
     * over the open windows of the unrealized gain against the remaining cost. [null, null] when nothing is held.
     *
     * @return array{0: ?float, 1: ?float} [gain %, gain EUR]
     */
    public static function openGain(array $perf): array
    {
        $gain = 0.0;
        $cost = 0.0;
        foreach ($perf['windows'] ?? [] as $window) {
            if (empty($window['is_open']) || (float) ($window['remaining_cost_eur'] ?? 0.0) <= 0.0) {
                continue;
            }
            $gain += (float) ($window['unrealized_gain_eur'] ?? 0.0);
            $cost += (float) $window['remaining_cost_eur'];
        }

        return $cost > 0.0 ? [$gain / $cost * 100.0, $gain] : [null, null];
    }
}

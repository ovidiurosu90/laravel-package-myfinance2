<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * The warnings an order raises against the symbol's tier reading (the /watchlist-symbols Tier and Tier History lines),
 * evaluated on the order create / edit form (the banner) and again on save. A serious warning asks for a confirmation
 * on save; a soft one is a banner only. Which codes are serious is set in config
 * (myfinance2.watchlistsymbols.order_warnings.levels), decided by the calibration command
 * (finance:order-warning-review), not here.
 *
 * Input: the reading's facts (TierReadingBuilder for a live reading, TierReadingAsOf for a rebuilt one). A fact that
 * is null (not known for that reading: a rebuilt reading has no portfolio, quote or superstar) raises nothing.
 *
 * Pure: no database, no config read outside fromConfig().
 */
final class OrderTierWarnings
{
    public const LEVEL_SERIOUS = 'serious';
    public const LEVEL_SOFT    = 'soft';
    public const LEVEL_OFF     = 'off';

    public const VERDICT_OK      = 'ok';
    public const VERDICT_SOFT    = 'soft';
    public const VERDICT_AGAINST = 'against';
    public const VERDICT_NONE    = 'none';

    public const BUY_WEAK_TIER           = 'BUY_WEAK_TIER';
    public const BUY_SILVER              = 'BUY_SILVER';
    public const BUY_TAKE_PROFIT         = 'BUY_TAKE_PROFIT';
    public const BUY_DETERIORATING       = 'BUY_DETERIORATING';
    public const BUY_OVER_MAX_POSITION   = 'BUY_OVER_MAX_POSITION';
    public const BUY_RARE_HIGH           = 'BUY_RARE_HIGH';
    public const BUY_NEAR_PEAK           = 'BUY_NEAR_PEAK';
    public const BUY_HEALTH_WEIGHED_DOWN = 'BUY_HEALTH_WEIGHED_DOWN';
    public const SELL_SUPERSTAR          = 'SELL_SUPERSTAR';
    public const SELL_RARE_LOW           = 'SELL_RARE_LOW';
    public const SELL_RANGE_LOW          = 'SELL_RANGE_LOW';
    public const NOT_ON_WATCHLIST        = 'NOT_ON_WATCHLIST';
    public const STALE_QUOTE             = 'STALE_QUOTE';

    /** code => level, in the order the banner lists them. */
    public const DEFAULT_LEVELS = [
        self::BUY_WEAK_TIER           => self::LEVEL_SERIOUS,
        self::BUY_SILVER              => self::LEVEL_SOFT,
        self::BUY_TAKE_PROFIT         => self::LEVEL_SERIOUS,
        self::BUY_DETERIORATING       => self::LEVEL_SOFT,
        self::BUY_OVER_MAX_POSITION   => self::LEVEL_SERIOUS,
        self::BUY_RARE_HIGH           => self::LEVEL_SOFT,
        self::BUY_NEAR_PEAK           => self::LEVEL_SOFT,
        self::BUY_HEALTH_WEIGHED_DOWN => self::LEVEL_SOFT,
        self::SELL_SUPERSTAR          => self::LEVEL_SERIOUS,
        self::SELL_RARE_LOW           => self::LEVEL_SOFT,
        self::SELL_RANGE_LOW          => self::LEVEL_SOFT,
        self::NOT_ON_WATCHLIST        => self::LEVEL_SOFT,
        self::STALE_QUOTE             => self::LEVEL_SOFT,
    ];

    /** Short names, the bold start of each line in the banner and the review command's rows. */
    public const LABELS = [
        self::BUY_WEAK_TIER           => 'Weak tier',
        self::BUY_SILVER              => 'Silver tier',
        self::BUY_TAKE_PROFIT         => 'Take profit',
        self::BUY_DETERIORATING       => 'Deteriorating',
        self::BUY_OVER_MAX_POSITION   => 'Position size',
        self::BUY_RARE_HIGH           => 'Rare high',
        self::BUY_NEAR_PEAK           => 'Near peak',
        self::BUY_HEALTH_WEIGHED_DOWN => 'Portfolio weighed down',
        self::SELL_SUPERSTAR          => 'Superstar',
        self::SELL_RARE_LOW           => 'Rare low',
        self::SELL_RANGE_LOW          => 'Range-bound low',
        self::NOT_ON_WATCHLIST        => 'No tier reading',
        self::STALE_QUOTE             => 'Stale quote',
    ];

    /** Codes that need live data (the portfolio, the quote, the superstar): never raised on a rebuilt reading. */
    public const LIVE_ONLY = [
        self::BUY_OVER_MAX_POSITION, self::BUY_HEALTH_WEIGHED_DOWN, self::SELL_SUPERSTAR, self::STALE_QUOTE,
    ];

    private const WEAK_TIERS   = [TierCalculationService::BRONZE, TierCalculationService::RUST];
    private const STRONG_TIERS = [TierCalculationService::PLATINUM, TierCalculationService::GOLD];

    /**
     * @param array<string, string> $_levels code => LEVEL_*; a code left out uses its default
     * @param bool $_softenWeakTier BUY_WEAK_TIER is soft when Index Gap Settled is Silver or better (the symbol
     *                              falls with the market, not on its own)
     */
    public function __construct(
        private readonly array $_levels = [],
        private readonly float $_maxPositionPct = 10.0,
        private readonly bool $_softenWeakTier = true
    )
    {
    }

    public static function fromConfig(): self
    {
        return new self(
            (array) config('myfinance2.watchlistsymbols.order_warnings.levels', []),
            (float) config('myfinance2.watchlistsymbols.tier_history.max_position_pct', 10),
            (bool) config('myfinance2.watchlistsymbols.order_warnings.soften_weak_tier_on_index_gap', false)
        );
    }

    /**
     * @param string $action BUY or SELL
     * @param array  $facts  the reading's facts: has_reading, symbol, effective_tier, tier_basis (the approach in use
     *                       and its figure, e.g. "Market Settled, - 10.6 %"), index_gap_settled_tier, settled_tier,
     *                       take_profit, signal, deteriorating_days, drop_from, rare_share_pct, range_zone,
     *                       near_peak_windows, near_peak_closest_pct, superstar, health_signal, bronze_rust_pct,
     *                       bronze_rust_target_pct, position_pct_after, stale_quote_age
     *
     * @return list<array{code: string, level: string, label: string, text: string}> serious first, then soft
     */
    public function evaluate(string $action, array $facts): array
    {
        $raised = [];
        foreach ($this->_raise(strtoupper($action), $facts) as $code => [$text, $softened]) {
            $level = $this->level($code);
            if ($softened && $level === self::LEVEL_SERIOUS) {
                $level = self::LEVEL_SOFT;
            }
            if ($level === self::LEVEL_OFF) {
                continue;
            }
            $raised[] = ['code' => $code, 'level' => $level, 'label' => self::LABELS[$code], 'text' => $text];
        }
        usort($raised, fn ($a, $b) => ($a['level'] === self::LEVEL_SERIOUS ? 0 : 1)
            <=> ($b['level'] === self::LEVEL_SERIOUS ? 0 : 1));

        return $raised;
    }

    public function level(string $code): string
    {
        $level = strtolower((string) ($this->_levels[$code] ?? self::DEFAULT_LEVELS[$code] ?? self::LEVEL_SOFT));

        return in_array($level, [self::LEVEL_SERIOUS, self::LEVEL_SOFT, self::LEVEL_OFF], true)
            ? $level : self::LEVEL_SOFT;
    }

    /**
     * The verdict of a set of warnings: against the tiers (a serious one), soft warnings only, or no warning.
     */
    public static function verdict(array $warnings): string
    {
        $levels = array_column($warnings, 'level');
        if (in_array(self::LEVEL_SERIOUS, $levels, true)) {
            return self::VERDICT_AGAINST;
        }

        return empty($levels) ? self::VERDICT_OK : self::VERDICT_SOFT;
    }

    /**
     * The serious codes of $warnings that are not in $acknowledged (the codes the confirmation showed).
     *
     * @return string[]
     */
    public static function unacknowledged(array $warnings, array $acknowledged): array
    {
        $serious = array_column(self::serious($warnings), 'code');

        return array_values(array_diff($serious, $acknowledged));
    }

    public static function serious(array $warnings): array
    {
        return array_values(array_filter($warnings, fn ($w) => ($w['level'] ?? null) === self::LEVEL_SERIOUS));
    }

    public static function soft(array $warnings): array
    {
        return array_values(array_filter($warnings, fn ($w) => ($w['level'] ?? null) === self::LEVEL_SOFT));
    }

    /**
     * Whether two warning sets name the same codes at the same levels (the text may differ).
     */
    public static function sameSet(array $a, array $b): bool
    {
        $key = fn (array $warnings) => array_map(fn ($w) => $w['code'] . ':' . $w['level'], $warnings);
        $ka  = $key($a);
        $kb  = $key($b);
        sort($ka);
        sort($kb);

        return $ka === $kb;
    }

    /**
     * @return array<string, array{0: string, 1: bool}> code => [text, softened], in DEFAULT_LEVELS order
     */
    private function _raise(string $action, array $facts): array
    {
        $raised = [];
        if (empty($facts['has_reading'])) {
            $raised[self::NOT_ON_WATCHLIST] = [($facts['symbol'] ?? 'The symbol') . ' is not on the watchlist and'
                . ' has no tier reading, so this order cannot be checked against the tiers.', false];
        } elseif ($action === 'BUY') {
            $raised += $this->_buy($facts);
        } elseif ($action === 'SELL') {
            $raised += $this->_sell($facts);
        }
        if (!empty($facts['stale_quote_age'])) {
            $raised[self::STALE_QUOTE] = ['the live quote is ' . $facts['stale_quote_age'] . ' old; check the'
                . ' limit price.', false];
        }

        return array_replace(array_intersect_key(self::DEFAULT_LEVELS, $raised), $raised);
    }

    private function _buy(array $facts): array
    {
        $raised = [];
        $tier   = $facts['effective_tier'] ?? null;
        $rank   = TierCalculationService::tierRank($tier);
        $inUse  = 'the tier in use is ' . self::_tierText($tier, $facts['tier_basis'] ?? null);
        if (in_array($tier, self::WEAK_TIERS, true)) {
            $gapTier  = $facts['index_gap_settled_tier'] ?? null;
            $softened = $this->_softenWeakTier && $gapTier !== null
                && TierCalculationService::tierRank($gapTier) >= TierCalculationService::tierRank(
                    TierCalculationService::SILVER
                );
            $raised[self::BUY_WEAK_TIER] = [$inUse . '. Buying adds to the weak side of the portfolio.'
                . ($softened ? ' Index Gap Settled is ' . TierCalculationService::tierLabel($gapTier) . ': it falls'
                    . ' with the market rather than on its own.' : ''), $softened];
        } elseif ($tier === TierCalculationService::SILVER) {
            $raised[self::BUY_SILVER] = [$inUse . ', under the Gold line: buying adds to the middle of the'
                . ' portfolio, not its strong core.', false];
        }
        if (!empty($facts['take_profit'])) {
            $raised[self::BUY_TAKE_PROFIT] = ['a weak tier with the open position in profit reads TAKE PROFIT: the'
                . ' tiers say sell all or part, not buy more.', false];
        }
        $signal = $facts['signal'] ?? null;
        if ($signal === SettledTierResolver::SIGNAL_DETERIORATING) {
            $raised[self::BUY_DETERIORATING] = [self::_dropText($facts), false];
        }
        $after = $facts['position_pct_after'] ?? null;
        if ($after !== null && $after > $this->_maxPositionPct) {
            $raised[self::BUY_OVER_MAX_POSITION] = ['after this order the position would be '
                . self::_pct((float) $after) . ' of the portfolio, above the ' . self::_pct($this->_maxPositionPct)
                . ' limit.', false];
        }
        if ($signal === SettledTierResolver::SIGNAL_RARE_HIGH) {
            $raised[self::BUY_RARE_HIGH] = ['today\'s tier is above its settled tier and rarely this high'
                . (isset($facts['rare_share_pct'])
                    ? ' (at or above it on ' . self::_pct((float) $facts['rare_share_pct']) . ' of the last 12 months)'
                    : '')
                . ': an expensive entry.', false];
        }
        if (!empty($facts['near_peak_windows'])) {
            $raised[self::BUY_NEAR_PEAK] = ['the price is near its '
                . implode(', ', array_map('strtoupper', $facts['near_peak_windows'])) . ' peak'
                . (count($facts['near_peak_windows']) > 1 ? 's' : '')
                . (isset($facts['near_peak_closest_pct'])
                    ? ' (closest ' . TierApproachComparison::formatShort((float) $facts['near_peak_closest_pct'], '%')
                        . ')'
                    : '')
                . ': buying near a high.', false];
        }
        if (($facts['health_signal'] ?? null) === 'warning' && $rank !== null
            && $rank <= TierCalculationService::tierRank(TierCalculationService::SILVER)
        ) {
            $raised[self::BUY_HEALTH_WEIGHED_DOWN] = [(isset($facts['bronze_rust_pct'])
                    ? 'Bronze + Rust hold ' . self::_pct((float) $facts['bronze_rust_pct']) . ' of the portfolio'
                        . (isset($facts['bronze_rust_target_pct'])
                            ? ' (target under ' . self::_pct((float) $facts['bronze_rust_target_pct']) . ')' : '')
                    : 'the Portfolio Health card reads weighed down')
                . '. Buying a ' . TierCalculationService::tierLabel($tier) . ' does not strengthen the core.', false];
        }

        return $raised;
    }

    private function _sell(array $facts): array
    {
        $raised    = [];
        $superstar = $facts['superstar'] ?? null;
        if (!empty($superstar)) {
            $raised[self::SELL_SUPERSTAR] = ['Platinum or Gold under ' . $superstar['count'] . ' of the '
                . $superstar['total'] . ' tier approaches: strong whichever way its return is measured.', false];
        }
        $settled = $facts['settled_tier'] ?? null;
        if (($facts['signal'] ?? null) === SettledTierResolver::SIGNAL_RARE_LOW
            && in_array($settled, self::STRONG_TIERS, true)
        ) {
            $raised[self::SELL_RARE_LOW] = ['a rare dip on a symbol settled '
                . TierCalculationService::tierLabel($settled) . ': the tiers read it as a chance to add, not to'
                . ' sell.', false];
        }
        if (($facts['range_zone'] ?? null) === RangeBoundPattern::ZONE_LOW) {
            $raised[self::SELL_RANGE_LOW] = ['range-bound and near the 52-week low: the pattern reads ADD (buy on'
                . ' the dip), not sell.', false];
        }

        return $raised;
    }

    /**
     * "Rust (Market Settled, - 10.6 %)", or the tier alone without its basis.
     */
    private static function _tierText(?string $tier, ?string $basis): string
    {
        $label = $tier !== null ? TierCalculationService::tierLabel($tier) : 'Unrated';

        return $label . ($basis !== null && $basis !== '' ? ' (' . $basis . ')' : '');
    }

    private static function _dropText(array $facts): string
    {
        $days = (int) ($facts['deteriorating_days'] ?? 0);
        $from = $facts['drop_from'] ?? null;

        return 'two tiers or more below its settled'
            . ($from !== null ? ' ' . TierCalculationService::tierLabel($from) : '') . ' tier'
            . ($days > 0 ? ' for ' . $days . ' trading days' : '') . '; the tier now follows today.';
    }

    /**
     * "12.5 %", "15 %" for a whole number.
     */
    private static function _pct(float $value): string
    {
        $decimals = round($value, 1) == round($value) ? 0 : 1;

        return MoneyFormat::get_formatted_number_plain($value, $decimals) . "\u{00A0}%";
    }
}

<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * The range-bound pattern: a stock you trade well whose price swings widely but ends the year about where it
 * started. Its 1Y readings sit near zero (so its tiers call it weak) while buying near the 52-week low and
 * selling near the high is among your best trading. This reads the swing; the tier keeps reading the trend.
 *
 * A symbol is range-bound when all of these hold:
 *   - you have traded it (your own results, not a market stand-in);
 *   - the 1Y market return is small: under max_abs_1y_pct either way;
 *   - the 52-week range is wide: the closing low under max_low_high_pct of the closing high;
 *   - your own results are good: Own Lifetime at min_own_tier or better (for a symbol you exited, your realized
 *     lifetime return), or at least min_completed completed trades (closed windows) with min_win_rate_pct or more
 *     of them won.
 *
 * Where today's price sits in that range (0 % at the low, 100 % at the high) gives the zone and the action:
 * near the high, take the swing profit (TRIM); near the low, add on the dip (ADD); in between, HOLD. Without an
 * open position: WAIT, WATCH, BUY. Pure: no database, no config read outside fromConfig().
 *
 * Backtest note (phase 4, TierSignalBacktest + PositionReadingAsOf, 2026-10-13; revisit with more data):
 *   On one portfolio's stored history (2025-03 to 2026-10; a day needs a full year of closes; your record counted as
 *   Own Lifetime only), judged against VUSA.AS 126 trading days after each episode's first day (base rate: a symbol
 *   trailed the index on 56 % of days, beat it on 44 %, median -3.3 points):
 *   - ADD (held, near the low): supported. Beat the index in 64 % of 22 episodes, median +9.3 points.
 *   - BUY (traded before, near the low): supported on a tiny sample. Beat it in all 5, median +45 points.
 *   - TRIM (held, near the high): no edge. Trailed in 56 % of 57 episodes (the base rate), median -2.5 points: selling
 *     near the high did no better than any day, and no worse.
 *   Done since: TRIM is an inbox card, not emailed (myfinance2.alerts.peak_proximity.range_emails lists ADD and BUY).
 *   If a rerun (finance:tier-signal-backtest) shows TRIM has an edge, list RANGE_HIGH there again; if it still has
 *   none, demand more for it (a higher near_high_pct, or RSI overbought as a condition rather than a severity) or drop
 *   its card (PeakProximityRangeSignals); the chip can keep reading the swing.
 */
final class RangeBoundPattern
{
    public const LABEL = 'Range-bound';

    public const ZONE_HIGH   = 'high';
    public const ZONE_MIDDLE = 'middle';
    public const ZONE_LOW    = 'low';

    public const ACTION_TRIM  = 'TRIM';
    public const ACTION_HOLD  = 'HOLD';
    public const ACTION_ADD   = 'ADD';
    public const ACTION_WAIT  = 'WAIT';
    public const ACTION_WATCH = 'WATCH';
    public const ACTION_BUY   = 'BUY';

    private const ZONE_LABELS = [
        self::ZONE_HIGH   => 'near the high',
        self::ZONE_MIDDLE => 'mid-range',
        self::ZONE_LOW    => 'near the low',
    ];

    /** zone => [action with an open position, action without one] */
    private const ACTIONS = [
        self::ZONE_HIGH   => [self::ACTION_TRIM, self::ACTION_WAIT],
        self::ZONE_MIDDLE => [self::ACTION_HOLD, self::ACTION_WATCH],
        self::ZONE_LOW    => [self::ACTION_ADD, self::ACTION_BUY],
    ];

    private const ACTION_HINTS = [
        self::ACTION_TRIM  => 'take the swing profit',
        self::ACTION_HOLD  => 'no edge until the price nears either end of the range',
        self::ACTION_ADD   => 'buy more on the dip',
        self::ACTION_WAIT  => 'too high to re-enter',
        self::ACTION_WATCH => 're-enter nearer the low',
        self::ACTION_BUY   => 'a good point to re-enter',
    ];

    private const OWN_BASES = [TierDecision::BASIS_ANNUALIZED_RETURN, TierDecision::BASIS_RAW_RETURN];

    private const NBSP = "\u{00A0}";

    public function __construct(
        private readonly float $_maxAbs1yPct = 10.0,
        private readonly float $_maxLowHighPct = 80.0,
        private readonly string $_minOwnTier = TierCalculationService::GOLD,
        private readonly int $_minCompleted = 2,
        private readonly float $_minWinRatePct = 75.0,
        private readonly float $_nearHighPct = 70.0,
        private readonly float $_nearLowPct = 30.0
    )
    {
    }

    public static function fromConfig(): self
    {
        $cfg = (array) config('myfinance2.watchlistsymbols.range_bound', []);

        return new self(
            (float) ($cfg['max_abs_1y_pct'] ?? 10.0),
            (float) ($cfg['max_low_high_pct'] ?? 80.0),
            strtoupper((string) ($cfg['min_own_tier'] ?? TierCalculationService::GOLD)),
            (int) ($cfg['min_completed'] ?? 2),
            (float) ($cfg['min_win_rate_pct'] ?? 75.0),
            (float) ($cfg['near_high_pct'] ?? 70.0),
            (float) ($cfg['near_low_pct'] ?? 30.0)
        );
    }

    /**
     * The range-bound reading of one symbol, or null when the pattern does not hold.
     *
     * @param array      $cat      CategorizationService entry: candidates.market_1y_pct, own_rule, closing_extremes,
     *                             is_benchmark
     * @param array      $perf     SymbolPerformanceService entry: window_count, win_rate, windows
     * @param float|null $priceEur today's price in EUR (live on the dashboard, the last close in the cron)
     *
     * @return array{zone: string, action: string, label: string, title: string, position_pct: float,
     *     low_high_pct: float, market_1y_pct: float, own_tier: ?string, own_value: ?float, wins: ?int,
     *     completed: ?int, is_owned: bool, low_native: ?float, low_date: ?string, high_native: ?float,
     *     high_date: ?string, add_line_native: ?float, trim_line_native: ?float, add_line_pct: float,
     *     trim_line_pct: float}|null
     */
    public function detect(array $cat, array $perf, ?float $priceEur): ?array
    {
        $market1y = $cat['candidates']['market_1y_pct'] ?? null;
        $lowEur   = (float) ($cat['closing_extremes']['low_eur'] ?? 0.0);
        $highEur  = (float) ($cat['closing_extremes']['high_eur'] ?? 0.0);
        if (!empty($cat['is_benchmark']) || (int) ($perf['window_count'] ?? 0) === 0
            || $market1y === null || abs((float) $market1y) >= $this->_maxAbs1yPct
            || $lowEur <= 0.0 || $highEur <= $lowEur || $priceEur === null || $priceEur <= 0.0
        ) {
            return null;
        }
        $lowHighPct = $lowEur / $highEur * 100.0;
        if ($lowHighPct >= $this->_maxLowHighPct) {
            return null;
        }

        [$ownTier, $ownValue] = $this->_ownResult($cat['own_rule'] ?? [], $perf);
        $wins      = isset($perf['win_rate']['wins']) ? (int) $perf['win_rate']['wins'] : null;
        $completed = isset($perf['win_rate']['completed']) ? (int) $perf['win_rate']['completed'] : null;
        if (!$this->_ownTierQualifies($ownTier) && !$this->_winRateQualifies($wins, $completed)) {
            return null;
        }

        $position = max(0.0, min(100.0, ($priceEur - $lowEur) / ($highEur - $lowEur) * 100.0));
        $zone     = $position >= $this->_nearHighPct
            ? self::ZONE_HIGH
            : ($position <= $this->_nearLowPct ? self::ZONE_LOW : self::ZONE_MIDDLE);
        $isOwned  = $this->_hasOpenPosition($perf);
        $action   = self::ACTIONS[$zone][$isOwned ? 0 : 1];

        $reading = [
            'zone'          => $zone,
            'action'        => $action,
            'label'         => self::label($zone),
            'position_pct'  => round($position, 1),
            'low_high_pct'  => round($lowHighPct, 1),
            'market_1y_pct' => round((float) $market1y, 2),
            'own_tier'      => $ownTier,
            'own_value'     => $ownValue !== null ? round((float) $ownValue, 2) : null,
            'wins'          => $wins,
            'completed'     => $completed,
            'is_owned'      => $isOwned,
        ] + $this->_levels($cat['closing_extremes']);
        $reading['title'] = self::title($reading);

        return $reading;
    }

    /**
     * "Range-bound, near the high" (the zone in words), shared by every view and the alert email.
     */
    public static function label(string $zone): string
    {
        return self::LABEL . ', ' . (self::ZONE_LABELS[$zone] ?? $zone);
    }

    /**
     * The explanation shown in every tooltip and in the alert email: why the symbol is range-bound, where today's
     * price sits and what that reads as.
     */
    public static function title(array $reading): string
    {
        $pct  = fn (float $v): string => MoneyFormat::get_formatted_number_plain($v, 0) . self::NBSP . '%';
        $own  = [];
        if ($reading['own_tier'] !== null && $reading['own_value'] !== null) {
            $own[] = 'Own Lifetime ' . TierApproachComparison::formatShort($reading['own_value'], '%/y') . ', '
                . TierCalculationService::tierLabel($reading['own_tier']);
        }
        if ($reading['completed'] !== null && $reading['completed'] > 0) {
            $own[] = $reading['wins'] . ' of ' . $reading['completed'] . ' completed trades won';
        }
        $action = $reading['action'];

        return self::LABEL . ': the price ends the year about where it started (1Y '
            . TierApproachComparison::formatShort($reading['market_1y_pct'], '%') . ') after a wide swing (52-week'
            . ' low at ' . $pct($reading['low_high_pct']) . ' of the high), and you trade it well ('
            . implode('; ', $own) . '). Today\'s price is at ' . $pct($reading['position_pct']) . ' of its 52-week'
            . ' range (0' . self::NBSP . '% the low, 100' . self::NBSP . '% the high), '
            . (self::ZONE_LABELS[$reading['zone']] ?? $reading['zone']) . ': ' . $action . ', '
            . self::ACTION_HINTS[$action] . '. The tier still reads the trend; this reads the swing.';
    }

    /**
     * "at 97 % of its 52-week range", for the alert email's subject and header and the inbox card.
     */
    public static function positionText(array $reading): string
    {
        return 'at ' . MoneyFormat::get_formatted_number_plain((float) $reading['position_pct'], 0) . self::NBSP
            . '% of its 52-week range';
    }

    /**
     * The 52-week range as price levels in the trade currency, high to low, for the alert email and the inbox card:
     * the high, the TRIM line, the ADD line and the low, each with how far it sits from today's price (positive
     * above it). The line of the reading's zone is flagged; a level without a price (no native extremes) is left
     * out.
     *
     * @return array<int, array{label: string, price: float, date: ?string, from_price_pct: ?float, in_zone: bool}>
     */
    public static function levelRows(array $reading, ?float $price): array
    {
        $pct  = fn (float $v): string => MoneyFormat::get_formatted_number_plain($v, 0) . self::NBSP . '%';
        $rows = [
            ['52-week high', $reading['high_native'] ?? null, $reading['high_date'] ?? null, false],
            [self::ACTION_TRIM . ' line, ' . $pct((float) $reading['trim_line_pct']) . ' of the range',
                $reading['trim_line_native'] ?? null, null, $reading['zone'] === self::ZONE_HIGH],
            [self::ACTION_ADD . ' line, ' . $pct((float) $reading['add_line_pct']) . ' of the range',
                $reading['add_line_native'] ?? null, null, $reading['zone'] === self::ZONE_LOW],
            ['52-week low', $reading['low_native'] ?? null, $reading['low_date'] ?? null, false],
        ];

        $levels = [];
        foreach ($rows as [$label, $level, $date, $inZone]) {
            if ($level === null) {
                continue;
            }
            $levels[] = [
                'label'          => $label,
                'price'          => (float) $level,
                'date'           => $date,
                'from_price_pct' => $price !== null && $price > 0.0 ? ((float) $level / $price - 1.0) * 100.0 : null,
                'in_zone'        => $inZone,
            ];
        }

        return $levels;
    }

    /**
     * The 52-week closing low and high in the trade currency with their dates, and the price lines of the zones
     * (ADD at or under the near-low line, TRIM at or above the near-high line), for the alert email and inbox.
     */
    private function _levels(array $extremes): array
    {
        $low  = isset($extremes['low_native']) ? (float) $extremes['low_native'] : null;
        $high = isset($extremes['high_native']) ? (float) $extremes['high_native'] : null;
        $line = fn (float $pct): ?float => $low !== null && $high !== null
            ? round($low + ($high - $low) * $pct / 100.0, 4)
            : null;

        return [
            'low_native'       => $low,
            'low_date'         => $extremes['low_date'] ?? null,
            'high_native'      => $high,
            'high_date'        => $extremes['high_date'] ?? null,
            'add_line_native'  => $line($this->_nearLowPct),
            'trim_line_native' => $line($this->_nearHighPct),
            'add_line_pct'     => $this->_nearLowPct,
            'trim_line_pct'    => $this->_nearHighPct,
        ];
    }

    /**
     * Your own lifetime return and its tier: the own-return rule's figure (Own Lifetime) when it is read on your
     * return; for a symbol you exited, which that rule tiers on the market, your realized lifetime return (CAGR)
     * instead. [null, null] when neither exists (a market stand-in is never your own result).
     *
     * @return array{0: ?string, 1: ?float}
     */
    private function _ownResult(array $ownRule, array $perf): array
    {
        if (in_array($ownRule['basis'] ?? null, self::OWN_BASES, true) && ($ownRule['tier'] ?? null) !== null) {
            return [$ownRule['tier'], isset($ownRule['value']) ? (float) $ownRule['value'] : null];
        }
        $realized = $perf['annualized_percentage_gain'] ?? null;
        if ($this->_hasOpenPosition($perf) || $realized === null) {
            return [null, null];
        }

        return [(new TierCalculationService())->getTier((float) $realized), (float) $realized];
    }

    private function _ownTierQualifies(?string $ownTier): bool
    {
        $rank = TierCalculationService::tierRank($ownTier);
        $min  = TierCalculationService::tierRank($this->_minOwnTier);

        return $rank !== null && $min !== null && $rank >= $min;
    }

    private function _winRateQualifies(?int $wins, ?int $completed): bool
    {
        return $wins !== null && $completed !== null && $completed >= $this->_minCompleted
            && $wins / $completed * 100.0 >= $this->_minWinRatePct;
    }

    private function _hasOpenPosition(array $perf): bool
    {
        foreach ($perf['windows'] ?? [] as $window) {
            if (!empty($window['is_open'])) {
                return true;
            }
        }

        return false;
    }
}

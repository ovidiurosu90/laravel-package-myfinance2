<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * The tier signal chip (phase 3): SettledTierResolver's signal turned into the words a chip and its tooltip show.
 *
 *   RARE_HIGH      "Rare high: SELL" (settled Silver or lower) or "TRIM" (settled Gold); a watch-only symbol shows
 *                  "Rare high" without an action (nothing to sell, an expensive entry).
 *   RARE_LOW       "Rare low: ADD" when held, "BUY" otherwise (only on a symbol settled Gold or Platinum).
 *   DETERIORATING  "Deteriorating: REVIEW" when held, "Deteriorating" without an action otherwise.
 *
 * One chip per symbol: a range-bound symbol is read on its swing and a weak tier in profit as TAKE PROFIT, so on
 * those the signal defers (deferred_to set, no chip) and its summary is added to the winning reading's tooltip
 * instead (noteOnWinner()). The benchmark never signals. Severity and caution flags need the live position and
 * indicators, so TierSignalSeverity adds them on the dashboard.
 *
 * Backtest note (phase 4, TierSignalBacktest, 2026-10-13; chips kept, alerts made inbox-only, revisit with more data):
 *   On one portfolio's stored history (about 22,000 symbol-days, 2025-03 to 2026-10, judged against VUSA.AS 21 / 63
 *   / 126 trading days after each episode's first day; base rate: a symbol trailed the index on 56 % of days at 126):
 *   - DETERIORATING (REVIEW): weak at consistency 50, supported at 60. After the drop-rule fix (a drop is measured
 *     from the tier before it, so one fall is one episode): trailed in 57 % of 51 episodes at 126 days (base 56 %),
 *     median -5.1 points against -3.3; with consistency 60, 67 % of 43, median -11.8. (Before the fix, falls split
 *     into several later-starting episodes and read 68 % / 81 %.)
 *   - RARE_HIGH (SELL / TRIM): no edge. Trailed in only 39-46 % at 126 days across the grid, median +4 points, mean
 *     +14 to +17 (a few big winners kept running; selling them capped the gain).
 *   - RARE_LOW (ADD / BUY): the wrong way. Beat the index in only 31-42 % at 126 days, median -10 points (strong
 *     names that dipped mostly kept falling, several into a deterioration).
 *   - The grid (rare share 10 / 20 / 30 x consistency 50 / 60) did not change the direction: that period rewarded
 *     momentum. Single path, short overlapping episodes (median 3-5 days): a sanity check, not proof.
 *   Done since: every tier signal alert is an inbox card (INFO), emailed only when listed in
 *   myfinance2.alerts.peak_proximity.tier_signal_emails (none by default). If a rerun (finance:tier-signal-backtest)
 *   confirms it:
 *   - Narrow the inbox cards too: make alertable() return DETERIORATING chips only (PeakProximityTierSignals and the
 *     alerts page's "not enabled" note both go through it).
 *   - Or read the signals with momentum: change the actions in SettledTierResolver::_withSignal() (e.g. a rare low on
 *     a strong name as a warning, not a buy) and their words in _advice() and LABELS; TierSignalSeverity's rules
 *     follow the action.
 *   - Or tune: rare_share_pct, consistency_pct, buy_settled_tiers (config myfinance2.watchlistsymbols.tier_history).
 *   The range-bound and TAKE PROFIT readings were backtested the same way; their notes are in RangeBoundPattern and
 *   WeakTierProfit (ADD / BUY and TAKE PROFIT supported, TRIM no edge).
 *
 * Pure: no database, no config read outside fromConfig().
 */
final class TierSignalChip
{
    public const LABELS = [
        SettledTierResolver::SIGNAL_RARE_HIGH     => 'Rare high',
        SettledTierResolver::SIGNAL_RARE_LOW      => 'Rare low',
        SettledTierResolver::SIGNAL_DETERIORATING => 'Deteriorating',
    ];

    /** Short words for the table's signal filter, per reading (the deferring readings included). */
    public const FILTER_RANGE_BOUND = 'Range-bound';
    public const FILTER_TAKE_PROFIT = 'Take profit';

    public const DEFERRED_RANGE_BOUND = 'range_bound';
    public const DEFERRED_TAKE_PROFIT = 'take_profit';

    private const NBSP = "\u{00A0}";

    public function __construct(private readonly int $_deterioratePersistDays = 20)
    {
    }

    public static function fromConfig(): self
    {
        return new self((int) config('myfinance2.watchlistsymbols.tier_history.deteriorate_persist_days', 20));
    }

    /**
     * The chip reading of one symbol, or null without a signal.
     *
     * @param array $cat CategorizationService entry: tier_signal (SettledTier::toArray()), today_tier, is_benchmark,
     *                   range_bound, take_profit
     *
     * @return array{signal: string, label: string, action: ?string, today_tier: ?string, settled_tier: ?string,
     *     share_pct: ?float, deteriorating_days: int, drop_from: ?string, span: string, deferred_to: ?string,
     *     summary: string, title: string, severity: ?string, reasons: string[], flags: string[]}|null
     */
    public function detect(array $cat): ?array
    {
        $ts     = $cat['tier_signal'] ?? [];
        $signal = $ts['signal'] ?? null;
        if ($signal === null || !isset(self::LABELS[$signal]) || !empty($cat['is_benchmark'])) {
            return null;
        }

        $reading = [
            'signal'             => $signal,
            'label'              => self::LABELS[$signal],
            'action'             => $ts['action'] ?? null,
            'today_tier'         => $cat['today_tier'] ?? null,
            'settled_tier'       => $ts['settled_tier'] ?? null,
            'share_pct'          => $signal === SettledTierResolver::SIGNAL_RARE_HIGH
                ? ($ts['at_or_above_today_pct'] ?? null)
                : ($signal === SettledTierResolver::SIGNAL_RARE_LOW ? ($ts['at_or_below_today_pct'] ?? null) : null),
            'deteriorating_days' => (int) ($ts['deteriorating_days'] ?? 0),
            'drop_from'          => $ts['drop_from'] ?? null,
            'span'               => self::_span($ts),
            'deferred_to'        => match (true) {
                !empty($cat['range_bound']) => self::DEFERRED_RANGE_BOUND,
                !empty($cat['take_profit']) => self::DEFERRED_TAKE_PROFIT,
                default                     => null,
            },
            'severity'           => null,
            'reasons'            => [],
            'flags'              => [],
        ];
        $reading['summary'] = $this->_summary($reading, $ts);
        $reading['title']   = self::title($reading);

        return $reading;
    }

    /**
     * The chip's tooltip: the label, why it holds, what to consider, then the severity and caution flags when
     * TierSignalSeverity has added them.
     */
    public static function title(array $reading): string
    {
        $text = $reading['label'] . ': ' . $reading['summary'] . ' ' . self::_advice($reading);
        if (!empty($reading['severity'])) {
            $text .= ' Severity: ' . ucfirst(strtolower($reading['severity']))
                . (!empty($reading['reasons']) ? ' (' . implode('; ', $reading['reasons']) . ')' : '') . '.';
        }
        if (!empty($reading['flags'])) {
            $text .= ' Caution: ' . implode('; ', $reading['flags']) . '.';
        }

        return $text;
    }

    /**
     * When the signal defers to the range-bound or TAKE PROFIT reading, that reading's tooltip names it too, so one
     * chip still tells the whole story ("Also a rare high: ...").
     */
    public static function noteOnWinner(array $cat): array
    {
        $chip = $cat['tier_signal_chip'] ?? null;
        $key  = $chip['deferred_to'] ?? null;
        if ($key === null || empty($cat[$key]['title'])) {
            return $cat;
        }
        $kind = $chip['signal'] === SettledTierResolver::SIGNAL_DETERIORATING
            ? 'deteriorating'
            : 'a ' . strtolower($chip['label']);
        $cat[$key]['title'] .= ' Also ' . $kind . ': ' . $chip['summary'];

        return $cat;
    }

    /**
     * The chip a symbol shows: its reading unless it defers to the range-bound or TAKE PROFIT chip.
     */
    public static function shown(?array $cat): ?array
    {
        $chip = $cat['tier_signal_chip'] ?? null;

        return $chip !== null && empty($chip['deferred_to']) ? $chip : null;
    }

    /**
     * The chip that can raise a peak-proximity alert (signal RARE_HIGH / RARE_LOW / DETERIORATING): a shown chip
     * with an action. A watch-only rare high or deterioration has none (nothing to sell or review).
     */
    public static function alertable(?array $cat): ?array
    {
        $chip = self::shown($cat);

        return $chip !== null && !empty($chip['action']) ? $chip : null;
    }

    /**
     * "Silver or better on only 19 % of the last 12 months", "down from Platinum for 35 trading days" (two tiers or
     * more below it on each of those days): the alert email's subject and header, and the inbox card.
     */
    public static function shortText(array $chip): string
    {
        if ($chip['signal'] === SettledTierResolver::SIGNAL_DETERIORATING) {
            $days = (int) $chip['deteriorating_days'] . ' trading days';

            return empty($chip['drop_from'])
                ? 'two or more tiers down for ' . $days
                : 'down from ' . TierCalculationService::tierLabel($chip['drop_from']) . ' for ' . $days;
        }

        return TierCalculationService::tierLabel($chip['today_tier'])
            . ($chip['signal'] === SettledTierResolver::SIGNAL_RARE_HIGH ? ' or better' : ' or lower') . ' on only '
            . MoneyFormat::get_formatted_number_plain((float) $chip['share_pct'], 0) . self::NBSP . '% of '
            . ($chip['span'] ?? 'the last 12 months');
    }

    /**
     * The table filter words of a symbol: every reading that holds, the deferring tier signal included (so
     * "Rare high" also finds a range-bound TRIM that is one).
     *
     * @return string[]
     */
    public static function filterLabels(?array $cat): array
    {
        $labels = [];
        if (!empty($cat['range_bound'])) {
            $labels[] = self::FILTER_RANGE_BOUND;
        }
        if (!empty($cat['take_profit'])) {
            $labels[] = self::FILTER_TAKE_PROFIT;
        }
        if (!empty($cat['tier_signal_chip']['label'])) {
            $labels[] = $cat['tier_signal_chip']['label'];
        }

        return $labels;
    }

    /**
     * Why the signal holds, in one sentence: "Silver today, but Silver or better on only 19 % of the last 12 months
     * (settled Rust)."; for a deterioration the resolver's own sentence.
     */
    private function _summary(array $reading, array $ts): string
    {
        if ($reading['signal'] === SettledTierResolver::SIGNAL_DETERIORATING) {
            return lcfirst((string) ($ts['explanation'] ?? ''));
        }

        $isHigh = $reading['signal'] === SettledTierResolver::SIGNAL_RARE_HIGH;
        $today  = (string) TierCalculationService::tierLabel($reading['today_tier']);
        $text   = $today . ' today, but ' . $today . ($isHigh ? ' or better' : ' or lower') . ' on only '
            . MoneyFormat::get_formatted_number_plain((float) $reading['share_pct'], 0) . self::NBSP . '% of '
            . $reading['span'] . ' (settled ' . TierCalculationService::tierLabel($reading['settled_tier']) . ').';

        // A rare low two tiers down is also counting toward the drop rule: say how far it has come.
        if (!$isHigh && $reading['deteriorating_days'] > 0) {
            $text .= ' Two or more tiers below it for ' . $reading['deteriorating_days'] . ' of the '
                . $this->_deterioratePersistDays . ' trading days after which the tier follows today (Deteriorating).';
        }

        return $text;
    }

    private static function _advice(array $reading): string
    {
        return match ($reading['action']) {
            SettledTierResolver::ACTION_SELL   => 'A weak name rarely reads this well: consider selling.',
            SettledTierResolver::ACTION_TRIM   => 'A reading this high rarely lasts: consider trimming.',
            SettledTierResolver::ACTION_ADD    => 'A rare dip on a strong name: consider adding.',
            SettledTierResolver::ACTION_BUY    => 'A rare dip on a strong name: consider buying.',
            SettledTierResolver::ACTION_REVIEW => 'Review the thesis.',
            default => $reading['signal'] === SettledTierResolver::SIGNAL_RARE_HIGH
                ? 'Not held, so nothing to sell; an expensive entry.'
                : 'Not held; not a buy while it lasts.',
        };
    }

    /**
     * "the last 12 months", or "the days since <first day>" while the history is shorter (as the settled tier's
     * explanation words it).
     */
    private static function _span(array $ts): string
    {
        $first = (string) ($ts['first_date'] ?? '');
        $last  = (string) ($ts['last_date'] ?? '');
        if ($first === '' || $last === '' || (strtotime($last) - strtotime($first)) / 86400 >= 330) {
            return 'the last 12 months';
        }

        return 'the days since ' . $first;
    }
}

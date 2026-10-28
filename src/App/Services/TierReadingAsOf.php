<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;

/**
 * A symbol's tier reading as it read on a past day, rebuilt from the stored daily tier history (symbol_tier_history)
 * and the stored closes, in TierReadingBuilder's shape: for orders and trades made before readings were saved, a
 * manual trade dated in the past, and the order warning review (finance:order-warning-review). The same no-look-ahead
 * rules as the signal backtest (TierSignalBacktest).
 *
 * The reading of a day D is the reading of the last stored trading day R on or before D: today's values are R's own
 * row, the history behind them the rows of [R - lookback, R - 1].
 *   - Approaches: TierApproachComparison::cells() on a categorization entry built from R's row (1Y market return, your
 *     own return), so Own Lifetime, Market Snapshot, Market Settled and both Index Gap approaches use the live code.
 *     Market 2Y Snapshot and Own Alpha are not stored per day: shown as "-".
 *   - Tier in use and the tier signal: SettledTierResolver on the window (no manual override).
 *   - Range-bound and TAKE PROFIT: PositionReadingAsOf (its approximations apply); near peak from the stored closes.
 *   - Not rebuilt: manual overrides, the share of the portfolio, the superstar star.
 * Before the stored history (or without a row on or before D): source none, no reading.
 */
final class TierReadingAsOf
{
    public const SOURCE_REBUILT = 'rebuilt';
    public const SOURCE_NONE    = 'none';

    /** The exit-zone windows of DrawdownService, in calendar days. */
    private const PEAK_WINDOWS = ['3m' => 91, '6m' => 182, '1y' => 365, '2y' => 730];

    private const NOT_STORED = 'Not stored per day, so not rebuilt for a past date.';

    private TierCalculationService $_tiers;

    public function __construct(
        private readonly SettledTierResolver $_resolver,
        private readonly PositionReadingAsOf $_positions,
        private readonly NearPeakReading $_nearPeak,
        private readonly TierSignalChip $_chip,
        private readonly TierHistoryPresenter $_presenter,
        private readonly int $_lookbackDays = 365,
        private readonly string $_historyStart = '2025-01-01'
    )
    {
        $this->_tiers = new TierCalculationService();
    }

    public static function fromConfig(): self
    {
        $cfg = config('myfinance2.watchlistsymbols.tier_history', []);

        return new self(
            SettledTierResolver::fromConfig(),
            new PositionReadingAsOf(RangeBoundPattern::fromConfig(), WeakTierProfit::fromConfig()),
            NearPeakReading::fromConfig(),
            TierSignalChip::fromConfig(),
            new TierHistoryPresenter((float) ($cfg['split_share_pct'] ?? 15)),
            (int) ($cfg['lookback_days'] ?? 365),
            (string) ($cfg['start_date'] ?? '2025-01-01')
        );
    }

    /**
     * @param array $rows      the symbol's stored rows, date => row ascending (TierHistoryRepository), up to $day
     * @param array $indexRows VUSA.AS's stored rows, the same shape
     * @param array $closes    the symbol's closes, date => close ascending, two years back from $day
     *
     * @return array{source: string, as_of: ?string, reading: array} the reading carries 'facts' and 'position' (null)
     */
    public function read(string $symbol, string $day, array $rows, array $indexRows, array $closes): array
    {
        $asOf = $day >= $this->_historyStart ? self::_lastOnOrBefore($rows, $day) : null;
        if ($asOf === null || ($rows[$asOf]['v'] ?? null) === null) {
            $why = $day < $this->_historyStart
                ? 'No tier history before ' . $this->_historyStart . '.'
                : 'No stored tier history for ' . $symbol . ' on or before ' . $day . '.';

            return ['source' => self::SOURCE_NONE, 'as_of' => null,
                'reading' => TierReadingBuilder::unrated($symbol, $why)
                    + ['facts' => ['has_reading' => false, 'symbol' => $symbol], 'position' => null]];
        }

        $row       = $rows[$asOf];
        $window    = $this->_window($rows, $asOf);
        $todayTier = $this->_tiers->getTier((float) $row['v']);
        $settled   = $this->_resolver->resolve(
            $symbol, $todayTier, $window, !empty($row['o']), $asOf,
            CategorizationTierHistory::measureOf((string) $row['b']), (float) $row['v']
        );
        $cat     = $this->_entry($symbol, $asOf, $row, $rows, $settled, $todayTier, $closes);
        $cells   = $this->_cells($symbol, $cat, $window, $this->_window($indexRows, $asOf), $indexRows, $asOf);
        $cat    += [
            'approach_cells' => $cells,
            'tier_history'   => $this->_presenter->forRow($symbol, $window, $settled, Carbon::parse($asOf)),
        ];
        $reading = TierReadingBuilder::fromCategorization($symbol, $cat);

        return ['source' => self::SOURCE_REBUILT, 'as_of' => $asOf, 'reading' => $reading + [
            'facts'    => TierReadingBuilder::facts($symbol, $cat, $reading['cells'], null, $this->_nearPeak),
            'position' => null,
        ]];
    }

    /**
     * The categorization entry of the day, with the chips: what the live pipeline would have built from the stored
     * history (no override, no superstar, no portfolio).
     */
    private function _entry(
        string $symbol,
        string $asOf,
        array $row,
        array $rows,
        SettledTier $settled,
        ?string $todayTier,
        array $closes
    ): array
    {
        $isBenchmark = $symbol === TierCalculationService::BENCHMARK_SYMBOL;
        $effective   = $settled->settledTier ?? $todayTier;
        [$yearCloses, $price] = $this->_closes($closes, $asOf, 365);
        $traded = false;
        foreach ($rows as $date => $r) {
            if ($date > $asOf) {
                break;
            }
            $traded = $traded || !empty($r['o']) || ($r['w'] ?? null) !== null;
        }
        $positions = $yearCloses === [] ? ['range_bound' => null, 'take_profit' => null]
            : $this->_positions->readings($row, $effective, $settled->settledTier, $yearCloses, $price, $traded);
        $own = isset($row['w']) ? (float) $row['w'] : null;

        $cat = [
            'effective_tier'    => $effective,
            'today_tier'        => $todayTier,
            'settled_tier'      => $settled->settledTier,
            'has_override'      => false,
            'is_benchmark'      => $isBenchmark,
            'is_owned'          => !empty($row['o']),
            'explanation'       => $settled->explanation,
            'tier_tooltip'      => $settled->explanation . ' Rebuilt from the tier history stored for ' . $asOf . '.',
            'tier_signal'       => $settled->toArray(),
            'candidates'        => ['market_1y_pct' => $row['m'] ?? null],
            'own_rule'          => $own === null ? [] : [
                'tier'        => $this->_tiers->getTier($own),
                'value'       => $own,
                'basis'       => TierDecision::BASIS_ANNUALIZED_RETURN,
                'basis_label' => TierDecision::basisLabel(TierDecision::BASIS_ANNUALIZED_RETURN),
            ],
            'alpha_vs_vusa_pct' => null,
            'momenta'           => [],
            'exit_zones'        => $this->_exitZones($closes, $asOf),
            'action'            => null,
            'superstar'         => null,
            'range_bound'       => $positions['range_bound'],
            'take_profit'       => $positions['take_profit'],
        ];
        $cat['tier_signal_chip'] = $this->_chip->detect($cat);
        $cat['near_peak']        = $this->_nearPeak->detect($cat);

        return TierSignalChip::noteOnWinner($cat);
    }

    /**
     * The approach cells of the day; the two approaches not stored per day shown as "-".
     */
    private function _cells(
        string $symbol,
        array $cat,
        array $window,
        array $indexWindow,
        array $indexRows,
        string $asOf
    ): array
    {
        $indexDay = self::_lastOnOrBefore($indexRows, $asOf);
        $index1y  = $indexDay !== null && isset($indexRows[$indexDay]['m']) ? (float) $indexRows[$indexDay]['m'] : null;
        $cells    = (new TierApproachComparison())->cells(
            $symbol, $cat, [$symbol => $window, TierCalculationService::BENCHMARK_SYMBOL => $indexWindow], $index1y,
            $asOf
        );
        foreach ([TierApproachComparison::MARKET_2Y_SNAPSHOT, TierApproachComparison::OWN_ALPHA] as $key) {
            $cells[$key] = [
                'value' => null, 'tier' => null, 'unit' => $cells[$key]['unit'] ?? TierApproachComparison::UNIT_PCT,
                'title' => self::NOT_STORED, 'flag' => false,
            ];
        }

        return $cells;
    }

    /**
     * The rows of [day - lookback, day - 1].
     */
    private function _window(array $rows, string $day): array
    {
        $from   = Carbon::parse($day)->subDays($this->_lookbackDays)->format('Y-m-d');
        $window = [];
        foreach ($rows as $date => $row) {
            if ($date >= $from && $date < $day) {
                $window[$date] = $row;
            }
        }

        return $window;
    }

    /**
     * Each window's peak among the closes of [day - window, day] and how far the day's close is from it, as
     * DrawdownService reports them (native closes here).
     */
    private function _exitZones(array $closes, string $day): array
    {
        $zones = [];
        foreach (self::PEAK_WINDOWS as $window => $days) {
            [$span, $price] = $this->_closes($closes, $day, $days);
            if ($span === [] || $price === null || $price <= 0.0) {
                $zones[$window] = null;
                continue;
            }
            $peak = max($span);
            $zones[$window] = $peak > 0.0 ? [
                'peak_price_native' => $peak,
                'peak_price_date'   => (string) array_search($peak, $span, true),
                'proximity_pct'     => round(($price / $peak - 1.0) * 100.0, 2),
            ] : null;
        }

        return $zones;
    }

    /**
     * @return array{0: array<string, float>, 1: ?float} the closes of [day - $days, day] and the last close on or
     *         before day; no closes when they do not reach back to the window's start (a partial window misreads)
     */
    private function _closes(array $closes, string $day, int $days): array
    {
        $from  = Carbon::parse($day)->subDays($days)->format('Y-m-d');
        $first = array_key_first($closes);
        if ($first === null || $first > Carbon::parse($from)->addDays(7)->format('Y-m-d')) {
            return [[], null];
        }
        $span = [];
        foreach ($closes as $date => $close) {
            if ($date >= $from && $date <= $day) {
                $span[$date] = (float) $close;
            }
        }

        return [$span, $span === [] ? null : end($span)];
    }

    private static function _lastOnOrBefore(array $series, string $day): ?string
    {
        $best = null;
        foreach (array_keys($series) as $date) {
            if ($date > $day) {
                break;
            }
            $best = (string) $date;
        }

        return $best;
    }
}

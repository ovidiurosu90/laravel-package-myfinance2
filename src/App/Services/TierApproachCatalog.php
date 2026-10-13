<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Services\TierApproachComparison as Cmp;

/**
 * The ways of measuring return a tier can be read from, as shown in the health card's approach comparison:
 * a unique name per approach (the pattern is whose return, horizon, how it is read: a snapshot is today's
 * reading, settled the median of the last 12 months; "Market" alone is the 1Y return), what it measures,
 * its tradeoffs, its verdict for the allocation, and the record of that decision.
 */
final class TierApproachCatalog
{
    public const VERDICT_CHOSEN    = 'chosen';
    public const VERDICT_RUNNER_UP = 'runner_up';
    public const VERDICT_NOT_USED  = 'not_used';

    /**
     * Which approach drives the allocation (the health card) and why, so the reasoning stays with the page.
     */
    public const DECISION = [
        'date'     => '2026-10-12',
        'approach'  => Cmp::MARKET_SETTLED,
        'runner_up' => Cmp::INDEX_GAP_SETTLED,
        'reason'    => 'it rates the stock rather than your trades, moves slowly, and its fixed 10% a year'
            . ' Gold line matches what the targets mean.',
        'why'      => [
            'Allocation changes over months, so it needs a measure that rates the stock, not your trades, and'
                . ' moves slowly. That rules out Own Lifetime and Own Alpha (your entry prices and timing) and'
                . ' the snapshots (one day\'s reading; Market 2Y Snapshot also keeps a stock high long after it'
                . ' collapsed).',
            'Market Settled and Index Gap Settled measure the same thing; only the Gold line differs. It is'
                . ' fixed at 10% a year (the index\'s long-run return) in Market Settled, and moves with what'
                . ' the index actually returned in Index Gap Settled.',
            'The fixed line matches what the targets mean: the share of capital compounding at more than 10%'
                . ' a year.',
            'In an unusually strong index year the moving line marks solid holdings weak only for not keeping'
                . ' up; in a normal year the two agree.',
            'Simpler: one stored history, easier to explain, and the rare high, rare low and deteriorating'
                . ' signals are built on it.',
        ],
        'risk'     => 'In a lasting downturn the fixed line turns most holdings Rust within weeks (the'
            . ' deterioration rule), so the card reads "weighed down" when selling is least wise. Countered by'
            . ' the rare low BUY / ADD signals and by the Index Gap Settled column, which shows whether a'
            . ' holding falls with the market or on its own.',
        'revisit'  => 'Switch to it if a downturn shows Market Settled pushing towards bad sells.',
        'note'     => 'Every approach gave a Warning when this was decided: the choice changes which holdings'
            . ' count as weak, not the verdict. If the verdict feels too harsh, the lever is the targets'
            . ' (60 / 25 / 15 %), not the approach.',
    ];

    /**
     * @return array<string, array{number: int, name: string, short: string, sub: string, measures: string,
     *         title: string, in_use: bool, pros: string[], cons: string[], verdict: string, verdict_text: string}>
     */
    public static function approaches(): array
    {
        $number = 0;
        $result = [];
        foreach (self::_descriptions() as $key => $description) {
            $result[$key] = ['number' => ++$number, 'in_use' => $key === self::DECISION['approach']]
                + $description + self::TRADEOFFS[$key];
        }

        return $result;
    }

    private static function _descriptions(): array
    {
        $minDays  = (int) config('watchlistsymbols.tier_history.min_history_days', 60);
        $relative = ' Matching the index is the Gold line: Platinum beats it by more than 5 points, Gold beats'
            . ' it, Silver trails it by up to 5, Bronze by 5 to 10, Rust by more than 10.';

        return [
            Cmp::OWN_LIFETIME       => [
                'name'     => 'Own Lifetime',
                'short'    => 'Lifetime',
                'sub'      => 'your return, old rule',
                'measures' => 'Your gain per year on the symbol, over all the periods you held it.',
                'title'    => 'Annualized (CAGR) once held a year; raw from 3 months, or the 1Y market return'
                    . ' for a newer holding, both marked *. The rule production used before.',
            ],
            Cmp::MARKET_SNAPSHOT    => [
                'name'     => 'Market Snapshot',
                'short'    => 'Snapshot',
                'sub'      => '1Y, today\'s reading',
                'measures' => 'Price today vs one year ago.',
                'title'    => 'The stock\'s return over the trailing 12 months, on today\'s reading.',
            ],
            Cmp::MARKET_SETTLED     => [
                'name'     => 'Market Settled',
                'short'    => 'Settled',
                'sub'      => '1Y, median of 12 months',
                'measures' => 'Middle value of the daily Market Snapshot readings over the last 12 months.',
                'title'    => 'The 1Y market return the symbol held or beat on at least half of the trading'
                    . ' days of the last 12 months. After 20 trading days two tiers below it (for Bronze, at'
                    . ' or under -5 %), the tier follows today; below ' . $minDays . ' days of history, today\'s'
                    . ' reading stands in (both marked *).',
            ],
            Cmp::MARKET_2Y_SNAPSHOT => [
                'name'     => 'Market 2Y Snapshot',
                'short'    => '2Y',
                'sub'      => '2Y CAGR, today\'s reading',
                'measures' => 'Price today vs two years ago, per year.',
                'title'    => 'The stock\'s annualized return (CAGR) over the trailing 2 years, on today\'s'
                    . ' reading.',
            ],
            Cmp::INDEX_GAP_SNAPSHOT => [
                'name'     => 'Index Gap Snapshot',
                'short'    => 'Gap',
                'sub'      => '1Y vs VUSA.AS, today\'s reading',
                'measures' => 'Market Snapshot minus the same figure for VUSA.AS, in points.',
                'title'    => 'The 1Y market return minus the VUSA.AS 1Y return, on today\'s reading.' . $relative,
            ],
            Cmp::INDEX_GAP_SETTLED  => [
                'name'     => 'Index Gap Settled',
                'short'    => 'Gap settled',
                'sub'      => '1Y vs VUSA.AS, median of 12 months',
                'measures' => 'Middle value of the daily Index Gap Snapshot readings over the last 12 months.',
                'title'    => 'The daily gap to VUSA.AS, settled like Market Settled.' . $relative,
            ],
            Cmp::OWN_ALPHA          => [
                'name'     => 'Own Alpha',
                'short'    => 'Alpha',
                'sub'      => 'your return vs VUSA.AS',
                'measures' => 'Your gain per year minus what VUSA.AS made over the same dates.',
                'title'    => 'Your own CAGR minus the VUSA.AS CAGR over the same dates you held (the raw'
                    . ' difference under a year, marked *).' . $relative,
            ],
        ];
    }

    private const TRADEOFFS = [
        Cmp::OWN_LIFETIME       => [
            'pros'         => [
                'What you actually earned on the symbol.',
                'Steady; available for every holding, unlisted ones included.',
            ],
            'cons'         => [
                'Anchored to your entry prices and timing (sunk-cost and disposition bias).',
                'Mixes holding periods of different years and lengths; says little about the stock now.',
                'Barely moves, so it misses a stock that turned weak and gives no signals.',
            ],
            'verdict'      => self::VERDICT_NOT_USED,
            'verdict_text' => 'rates your timing, not the stock.',
        ],
        Cmp::MARKET_SNAPSHOT    => [
            'pros'         => [
                'One yardstick for every symbol, independent of your trades.',
                'Current: reacts within days.',
            ],
            'cons'         => [
                'One day\'s reading: the price a year ago moves it as much as today\'s.',
                'Swings with the market; a single bad year turns a good company Rust.',
                'A momentum reading, not a measure of quality.',
            ],
            'verdict'      => self::VERDICT_NOT_USED,
            'verdict_text' => 'one day\'s reading.',
        ],
        Cmp::MARKET_SETTLED     => [
            'pros'         => [
                'One yardstick, independent of your trades, and steady from day to day.',
                'Today against settled gives the rare high and rare low signals.',
                'Follows a lasting drop after 20 trading days (deterioration rule).',
            ],
            'cons'         => [
                'Lags: a year of 1Y readings spans about two years of prices.',
                'Fixed lines: a market-wide year lifts or sinks every holding together.',
                'Needs stored daily history (backfill, 60 days) and is harder to explain.',
            ],
            'verdict'      => self::VERDICT_CHOSEN,
            'verdict_text' => 'drives this card.',
        ],
        Cmp::MARKET_2Y_SNAPSHOT => [
            'pros'         => [
                'Longer window: fewer flips than 1Y, no stored history needed.',
                'Annualized, so it reads on the same tier lines.',
            ],
            'cons'         => [
                'Still one start and one end date: a peak or a trough at either end skews it.',
                'Slow: a stock can stay Platinum long after a year of losses.',
                'No figure for symbols listed under 2 years.',
            ],
            'verdict'      => self::VERDICT_NOT_USED,
            'verdict_text' => 'misses stocks that collapsed.',
        ],
        Cmp::INDEX_GAP_SNAPSHOT => [
            'pros'         => [
                'Takes the market out: did it beat simply holding the index?',
                'The index is the real alternative for the money.',
            ],
            'cons'         => [
                'One day\'s reading of two noisy figures.',
                'Harsh in a strong index year, lenient in a weak one (a small loss in a crash is Platinum).',
                'One US large-cap benchmark for every region, sector and crypto.',
            ],
            'verdict'      => self::VERDICT_NOT_USED,
            'verdict_text' => 'one day\'s reading.',
        ],
        Cmp::INDEX_GAP_SETTLED  => [
            'pros'         => [
                'Steady and market-neutral: persistently beat or trailed the index.',
                'Closest to how fund ratings work (relative to a benchmark, smoothed).',
            ],
            'cons'         => [
                'Lags like Market Settled.',
                'Says nothing about absolute return: all can be Platinum while the portfolio loses money.',
                'Needs the index\'s stored history too; one US benchmark for everything.',
            ],
            'verdict'      => self::VERDICT_RUNNER_UP,
            'verdict_text' => 'the check next to Market Settled.',
        ],
        Cmp::OWN_ALPHA          => [
            'pros'         => [
                'Rates your decisions: your money against the same money put in the index on the same days.',
                'Available for every holding, unlisted ones included.',
            ],
            'cons'         => [
                'Depends on your timing, not on the stock; says little about holding it now.',
                'Under a year it is a raw difference and very noisy.',
                'Spans differ per symbol, like your lifetime return.',
            ],
            'verdict'      => self::VERDICT_NOT_USED,
            'verdict_text' => 'rates your timing.',
        ],
    ];
}

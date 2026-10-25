<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;

use ovidiuro\myfinance2\App\Models\TierSnapshot;

/**
 * The header line of the order and trade lists: per action, the last 12 months' orders or trades by the verdict of
 * their tier reading (against the tiers, soft warnings, no warning), how many of those against were confirmed through
 * the warning, and how many readings are rebuilt rather than live (counted apart: a rebuilt reading lacks overrides,
 * the portfolio and the superstar). Rows without a reading, or before the stored history, are counted as such.
 * Transfers are left out. Given each row's outcome against the index (the trade list's TradeVsIndex), each verdict also
 * carries the median of its rows' points: whether going against the tiers cost you, at a glance.
 */
final class TierVerdictSummary
{
    /**
     * @param iterable $items    orders or trades with their tierSnapshot loaded
     * @param callable $dateOf   fn ($item): ?Carbon, the day of the decision
     * @param array    $outcomes item id => ['points' => float] or null (TradeVsIndex::forTrades()); none for orders
     *
     * @return array<string, array{total: int, against: int, confirmed: int, soft: int, ok: int, rebuilt: array,
     *     unread: int, median: array}> action => counts; 'rebuilt' counts the rebuilt readings per verdict, 'median'
     *     the median points against the index per verdict (null without outcomes)
     */
    public static function build(iterable $items, callable $dateOf, ?Carbon $now = null, array $outcomes = []): array
    {
        $points  = [];
        $since   = ($now ?? Carbon::now())->copy()->subYear();
        $summary = [];
        foreach ($items as $item) {
            $date = $dateOf($item);
            if (!empty($item->is_transfer) || $date === null || $date < $since
                || !in_array($item->action, ['BUY', 'SELL'], true)
            ) {
                continue;
            }
            $row = $summary[$item->action] ?? [
                'total' => 0, 'against' => 0, 'confirmed' => 0, 'soft' => 0, 'ok' => 0,
                'rebuilt' => ['against' => 0, 'soft' => 0, 'ok' => 0], 'unread' => 0,
                'median'  => ['against' => null, 'soft' => null, 'ok' => null],
            ];
            $row['total']++;
            $snapshot = $item->tierSnapshot;
            $key      = match ($snapshot?->verdict) {
                OrderTierWarnings::VERDICT_AGAINST => 'against',
                OrderTierWarnings::VERDICT_SOFT    => 'soft',
                OrderTierWarnings::VERDICT_OK      => 'ok',
                default                            => null,
            };
            if ($key === null) {
                $row['unread']++;
            } else {
                $row[$key]++;
                $row['rebuilt'][$key] += $snapshot->source === TierSnapshot::SOURCE_REBUILT ? 1 : 0;
                $row['confirmed']     += $key === 'against' && $snapshot->acknowledged_at !== null ? 1 : 0;
                if ($outcomes !== [] && isset($outcomes[$item->id]['points'])) {
                    $points[$item->action][$key][] = (float) $outcomes[$item->id]['points'];
                }
            }
            $summary[$item->action] = $row;
        }
        foreach ($points as $action => $byVerdict) {
            foreach ($byVerdict as $key => $values) {
                $summary[$action]['median'][$key] = TierSignalBacktest::median($values);
            }
        }
        uksort($summary, fn ($a, $b) => $a === 'BUY' ? -1 : ($b === 'BUY' ? 1 : 0));

        return $summary;
    }

    /**
     * Each action's counts in words, for its tooltip: "BUY, 83 trades: 22 against (1 confirmed through the warning,
     * 21 rebuilt); 31 soft (31 rebuilt); 30 no warning (30 rebuilt)".
     *
     * @param string $noun "orders" or "trades"
     *
     * @return array<string, string> action => text
     */
    public static function lines(array $summary, string $noun): array
    {
        $lines = [];
        foreach ($summary as $action => $row) {
            $parts = [];
            foreach (['against' => 'against', 'soft' => 'soft', 'ok' => 'no warning'] as $key => $label) {
                if ($row[$key] > 0) {
                    $parts[] = $row[$key] . ' ' . $label . self::_extras($row, $key);
                }
            }
            if ($row['unread'] > 0) {
                $parts[] = $row['unread'] . ' without a reading';
            }
            $lines[$action] = $action . ', ' . $row['total'] . ' ' . ($row['total'] === 1 ? rtrim($noun, 's') : $noun)
                . ': ' . implode('; ', $parts);
        }

        return $lines;
    }

    /**
     * " (median + 3.2 pts vs the index, 1 confirmed through the warning, 28 rebuilt)", or nothing.
     */
    private static function _extras(array $row, string $key): string
    {
        $extras = [];
        $median = $row['median'][$key] ?? null;
        if ($median !== null) {
            $extras[] = 'median ' . TierApproachComparison::formatShort($median, 'pts') . ' vs the index';
        }
        if ($key === 'against' && $row['confirmed'] > 0) {
            $extras[] = $row['confirmed'] . ' confirmed through the warning';
        }
        if ($row['rebuilt'][$key] > 0) {
            $extras[] = $row['rebuilt'][$key] . ' rebuilt';
        }

        return empty($extras) ? '' : ' (' . implode(', ', $extras) . ')';
    }
}

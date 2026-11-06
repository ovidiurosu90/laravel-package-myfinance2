<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * A position's trades and the symbol's stock splits merged into one newest-first timeline, the rows of the trades
 * table the alert emails show under a position (emails/partials/position-trades). One builder for the peak-proximity
 * and the price alert emails, so both list a holding's history the same way.
 */
class PositionTimeline
{
    /**
     * Merge trades and stock splits into one newest-first timeline.
     *
     * @param iterable $trades Trade models (or objects with a Carbon `timestamp`)
     * @param iterable $splits StockSplit models (or objects with a Carbon `split_date`)
     *
     * @return array list of ['type' => 'trade'|'split', 'ts' => Carbon, 'data' => the model]
     */
    public static function merge(iterable $trades, iterable $splits): array
    {
        $timeline = [];
        foreach ($trades as $trade) {
            $timeline[] = ['type' => 'trade', 'ts' => $trade->timestamp, 'data' => $trade];
        }
        foreach ($splits as $split) {
            $timeline[] = ['type' => 'split', 'ts' => $split->split_date, 'data' => $split];
        }
        usort($timeline, fn (array $a, array $b): int => $b['ts'] <=> $a['ts']);

        return $timeline;
    }

    /**
     * Whether the timeline's trades come from more than one account (the table then names each trade's account).
     *
     * @param array $timeline merge()
     *
     * @return bool
     */
    public static function spansAccounts(array $timeline): bool
    {
        $accounts = [];
        foreach ($timeline as $event) {
            if ($event['type'] === 'trade') {
                $accounts[(string) ($event['data']->account_id ?? '')] = true;
            }
        }

        return count($accounts) > 1;
    }
}

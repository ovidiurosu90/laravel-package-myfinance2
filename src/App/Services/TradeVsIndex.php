<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Models\StatToday;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

/**
 * How each trade did against VUSA.AS since it was made, in points, for the trade list's "Vs Index" column (did
 * the tiers' call, or going against them, pay off?):
 *   - BUY: the trade's price to today's price (or to the SELL that closed it: the first SELL of the same symbol and
 *     account after it), less VUSA.AS over the same span;
 *   - SELL: the trade's price to today's price, less VUSA.AS, sign flipped: positive when the symbol then trailed
 *     the index, so the sale was right.
 * Today's price is the latest stored quote (stats_today, written by the quotes cron), else the last stored close;
 * VUSA.AS on a past day is its close on or before it. Prices are in each symbol's own currency (no FX).
 */
final class TradeVsIndex
{
    // A ratio further off than this means the trade price and the quote are in different units (e.g. GBP vs GBp).
    private const MAX_RATIO = 20.0;

    /**
     * @param Collection<int, Trade> $trades
     *
     * @return array<int, array{points: float, title: string}|null> trade id => comparison
     */
    public function forTrades(Collection $trades): array
    {
        $trades = $trades->filter(fn (Trade $t) => !$t->is_transfer && in_array($t->action, ['BUY', 'SELL'], true));
        if ($trades->isEmpty()) {
            return [];
        }
        $benchmark = TierCalculationService::BENCHMARK_SYMBOL;
        $first     = $trades->min(fn (Trade $t) => $t->timestamp->format('Y-m-d'));
        $index     = $this->_closes($benchmark, Carbon::parse($first)->subDays(10)->format('Y-m-d'));
        $latest    = $this->_latestPrices(array_merge($trades->pluck('symbol')->unique()->all(), [$benchmark]));
        $today     = Carbon::today()->format('Y-m-d');
        $indexNow  = $latest[$benchmark] ?? null;

        $result = [];
        foreach ($trades as $trade) {
            $start = $trade->timestamp->format('Y-m-d');
            $close = $trade->action === 'BUY' ? $this->_closingSell($trade, $trades) : null;
            $end   = $close !== null
                ? ['price' => (float) $close->unit_price, 'date' => $close->timestamp->format('Y-m-d'),
                    'label' => 'the SELL of ' . $close->timestamp->format('Y-m-d') . ' (trade #' . $close->id . ')']
                : (isset($latest[$trade->symbol]) ? ['price' => $latest[$trade->symbol]['price'], 'date' => $today,
                    'label' => 'today (' . $latest[$trade->symbol]['date'] . ')'] : null);
            $indexEnd = $end === null ? null
                : ($end['date'] === $today && $indexNow !== null ? $indexNow['price']
                    : StoredCloses::onOrBefore($index, array_keys($index), $end['date']));
            $result[$trade->id] = $end === null ? null : self::compare(
                $trade->action, (float) $trade->unit_price, $end['price'],
                StoredCloses::onOrBefore($index, array_keys($index), $start), $indexEnd, $start, $end['label']
            );
        }

        return $result;
    }

    /**
     * The comparison of one trade: its return and the index's over the same span, in points (signed so that positive
     * means the decision was right), and the tooltip text. Null without a price.
     */
    public static function compare(
        string $action,
        float $tradePrice,
        float $endPrice,
        ?float $indexStart,
        ?float $indexEnd,
        string $startDate,
        string $endLabel
    ): ?array
    {
        if ($tradePrice <= 0.0 || $endPrice <= 0.0 || $indexStart === null || $indexEnd === null
            || $indexStart <= 0.0 || $indexEnd <= 0.0
        ) {
            return null;
        }
        $ratio = $endPrice / $tradePrice;
        if ($ratio > self::MAX_RATIO || $ratio < 1.0 / self::MAX_RATIO) {
            return null;
        }
        $own    = ($ratio - 1.0) * 100.0;
        $bench  = ($indexEnd / $indexStart - 1.0) * 100.0;
        $sell   = strtoupper($action) === 'SELL';
        $points = $sell ? $bench - $own : $own - $bench;

        $title = ($sell ? 'Sold' : 'Bought') . ' on ' . $startDate . '; to ' . $endLabel . ' the symbol moved '
            . TierApproachComparison::formatShort($own, '%') . ', VUSA.AS '
            . TierApproachComparison::formatShort($bench, '%') . ': '
            . TierApproachComparison::formatShort($points, 'pts')
            . ($sell ? ' (positive: the symbol then trailed the index, so the sale was right).'
                : ' (positive: the buy beat the index).');

        return ['points' => round($points, 2), 'title' => $title];
    }

    /**
     * The SELL that closed a BUY: the first SELL of the same symbol and account after it.
     *
     * @param Collection<int, Trade> $trades
     */
    private function _closingSell(Trade $buy, Collection $trades): ?Trade
    {
        if ($buy->status !== 'CLOSED') {
            return null;
        }

        return $trades
            ->filter(fn (Trade $t) => $t->action === 'SELL' && $t->symbol === $buy->symbol
                && $t->account_id === $buy->account_id && $t->timestamp >= $buy->timestamp)
            ->sortBy(fn (Trade $t) => $t->timestamp->timestamp)
            ->first();
    }

    /**
     * @return array<string, float> date => close, ascending (stored closes only: no network)
     */
    private function _closes(string $symbol, string $from): array
    {
        $closes = [];
        $rows   = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->where('symbol', $symbol)->where('date', '>=', $from)->orderBy('date')->toBase()
            ->get(['date', 'unit_price']);
        foreach ($rows as $row) {
            $closes[substr((string) $row->date, 0, 10)] = (float) $row->unit_price;
        }

        return $closes;
    }

    /**
     * Each symbol's latest stored price: today's latest quote, else the last close of the last ten days.
     *
     * @return array<string, array{price: float, date: string}>
     */
    private function _latestPrices(array $symbols): array
    {
        $latest = [];
        $since  = Carbon::today()->subDays(10)->format('Y-m-d');
        $closes = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->whereIn('symbol', $symbols)->where('date', '>=', $since)->orderBy('date')->toBase()
            ->get(['symbol', 'date', 'unit_price']);
        foreach ($closes as $row) {
            $latest[$row->symbol] = ['price' => (float) $row->unit_price, 'date' => substr((string) $row->date, 0, 10)];
        }
        $quotes = StatToday::withoutGlobalScope(AssignedToUserScope::class)
            ->whereIn('symbol', $symbols)->where('timestamp', '>=', Carbon::today())->orderBy('timestamp')->toBase()
            ->get(['symbol', 'timestamp', 'unit_price']);
        foreach ($quotes as $row) {
            if ((float) $row->unit_price > 0.0) {
                $latest[$row->symbol] = ['price' => (float) $row->unit_price,
                    'date' => substr((string) $row->timestamp, 0, 16)];
            }
        }

        return $latest;
    }
}

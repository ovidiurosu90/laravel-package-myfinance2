<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Models\TierSnapshot;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

/**
 * Gives the orders and trades made before tier readings were saved a reading rebuilt once from the stored tier history
 * (RebuiltTierReadings), then locked like any other (finance:tier-snapshot-backfill). Orders first: a trade that filled
 * an order (same symbol and action) shares the order's reading, as a new trade would. An order's day is when it was
 * placed (else created), a trade's its timestamp; before the stored history the row is marked "no tier history".
 * In-kind transfers are not decisions and get none. A row that has a reading is left alone, unless forced by id.
 * The rows keep their updated_at.
 */
final class TierSnapshotBackfill
{
    public const ONLY_ALL    = 'all';
    public const ONLY_ORDERS = 'orders';
    public const ONLY_TRADES = 'trades';

    public const RESULT_CREATED = 'created';
    public const RESULT_SHARED  = 'shared with order';
    public const RESULT_SKIPPED = 'skipped';

    // order id => the check the run gave it, so a dry run shows the trades that would share it.
    private array $_orderChecks = [];

    public function __construct(private readonly RebuiltTierReadings $_rebuilt)
    {
    }

    public static function make(): self
    {
        return new self(app(RebuiltTierReadings::class));
    }

    /**
     * @param ?int $id    only this order or trade (with $only orders or trades)
     * @param bool $force replace an existing reading (with $id only)
     *
     * @return list<array{kind: string, id: int, symbol: string, action: string, date: string, result: string,
     *     source: ?string, verdict: ?string}>
     */
    public function run(int $userId, string $only, ?int $id, bool $force, bool $dryRun): array
    {
        $force = $force && $id !== null;
        $startDate = (string) config('myfinance2.watchlistsymbols.tier_history.start_date', '2025-01-01');
        $this->_rebuilt->preload($userId, $startDate);
        $rows = [];
        if ($only !== self::ONLY_TRADES) {
            foreach ($this->_orders($userId, $id, $force) as $order) {
                $rows[] = $this->_order($userId, $order, $dryRun);
            }
        }
        if ($only !== self::ONLY_ORDERS) {
            foreach ($this->_trades($userId, $id, $force) as $trade) {
                $rows[] = $this->_trade($userId, $trade, $dryRun);
            }
        }

        return $rows;
    }

    private function _order(int $userId, Order $order, bool $dryRun): array
    {
        $day   = ($order->placed_at ?? $order->created_at)->format('Y-m-d');
        $check = $this->_rebuilt->check($userId, $order->symbol, $order->action, $day);
        $this->_orderChecks[$order->id] = $check;
        if (!$dryRun) {
            $this->_point($order, TierSnapshot::createFromCheck($order->symbol, $order->action, $check, false));
        }

        return $this->_row('order', $order->id, $order->symbol, $order->action, $day, self::RESULT_CREATED, $check);
    }

    private function _trade(int $userId, Trade $trade, bool $dryRun): array
    {
        $day = $trade->timestamp->format('Y-m-d');
        if ($trade->is_transfer) {
            return $this->_row('trade', $trade->id, $trade->symbol, $trade->action, $day, self::RESULT_SKIPPED
                . ' (transfer)', null);
        }
        $order = $trade->linkedOrders->first(fn (Order $o) => $o->symbol === $trade->symbol
            && $o->action === $trade->action
            && ($o->tier_snapshot_id !== null || isset($this->_orderChecks[$o->id])));
        if ($order !== null) {
            // The order's reading: saved (now or before), or in a dry run the check it would be saved with.
            $snapshot = $dryRun ? null : $order->fresh()?->tierSnapshot()->withoutGlobalScope(
                AssignedToUserScope::class
            )->first();
            if ($snapshot !== null) {
                $this->_point($trade, $snapshot);
            }
            $shown = $snapshot !== null ? ['source' => $snapshot->source, 'verdict' => $snapshot->verdict]
                : ($this->_orderChecks[$order->id] ?? ['source' => $order->tierSnapshot?->source,
                    'verdict' => $order->tierSnapshot?->verdict]);

            return $this->_row('trade', $trade->id, $trade->symbol, $trade->action, $day,
                self::RESULT_SHARED . ' #' . $order->id, $shown);
        }
        $check = $this->_rebuilt->check($userId, $trade->symbol, $trade->action, $day);
        if (!$dryRun) {
            $this->_point($trade, TierSnapshot::createFromCheck($trade->symbol, $trade->action, $check, false));
        }

        return $this->_row('trade', $trade->id, $trade->symbol, $trade->action, $day, self::RESULT_CREATED, $check);
    }

    /**
     * Points an order or a trade to its reading without touching its updated_at or firing its events; a forced
     * reading's previous row is retired when nothing else points to it.
     */
    private function _point(Order|Trade $item, ?TierSnapshot $snapshot): void
    {
        if ($snapshot === null) {
            return;
        }
        $previous = $item->tier_snapshot_id;
        $item->tier_snapshot_id = $snapshot->id;
        $item->timestamps       = false;
        $item->saveQuietly();
        $item->timestamps       = true;
        if ($previous !== null && $previous !== $snapshot->id
            && !Order::withoutGlobalScope(AssignedToUserScope::class)->where('tier_snapshot_id', $previous)->exists()
            && !Trade::withoutGlobalScope(AssignedToUserScope::class)->where('tier_snapshot_id', $previous)->exists()
        ) {
            TierSnapshot::withoutGlobalScope(AssignedToUserScope::class)->find($previous)?->delete();
        }
    }

    private function _orders(int $userId, ?int $id, bool $force): iterable
    {
        return Order::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->when($id !== null, fn ($q) => $q->where('id', $id))
            ->when(!$force, fn ($q) => $q->whereNull('tier_snapshot_id'))
            ->orderBy('id')
            ->get();
    }

    private function _trades(int $userId, ?int $id, bool $force): iterable
    {
        return Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->with(['linkedOrders' => fn ($q) => $q->withoutGlobalScope(AssignedToUserScope::class)
                ->with(['tierSnapshot' => fn ($s) => $s->withoutGlobalScope(AssignedToUserScope::class)])])
            ->where('user_id', $userId)
            ->when($id !== null, fn ($q) => $q->where('id', $id))
            ->when(!$force, fn ($q) => $q->whereNull('tier_snapshot_id'))
            ->orderBy('timestamp')
            ->get();
    }

    private function _row(
        string $kind,
        int $id,
        string $symbol,
        string $action,
        string $day,
        string $result,
        ?array $check
    ): array
    {
        return [
            'kind'    => $kind,
            'id'      => $id,
            'symbol'  => $symbol,
            'action'  => $action,
            'date'    => $day,
            'result'  => $result,
            'source'  => $check['source'] ?? null,
            'verdict' => $check['verdict'] ?? null,
        ];
    }
}

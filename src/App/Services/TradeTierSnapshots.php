<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;

use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Models\TierSnapshot;
use ovidiuro\myfinance2\App\Models\Trade;

/**
 * The tier reading of the decision behind a trade (TierSnapshot). A trade is recorded after the fact, so there is no
 * warning banner and no confirmation: the reading and its verdict are kept, to judge later whether the decision
 * followed the tiers.
 *   - A trade filling an order (same symbol and action) shares the order's saved reading: the decision was the order's.
 *   - A manual trade dated today takes a live reading (OrderTierCheck); one with a past timestamp a reading rebuilt
 *     for that date from the stored tier history (RebuiltTierReadings).
 *   - Editing the symbol, the action or the trade's date is a new decision: a new reading. Other edits keep it.
 *   - A trade linked to an order later takes the order's reading when its own is rebuilt (or missing); a live one is
 *     kept. Unlinking keeps the reading the trade has.
 */
final class TradeTierSnapshots
{
    public function __construct(
        private readonly OrderTierCheck $_live,
        private readonly RebuiltTierReadings $_rebuilt
    )
    {
    }

    public static function make(): self
    {
        return new self(OrderTierCheck::make(), app(RebuiltTierReadings::class));
    }

    /**
     * The reading a trade with these values gets: a saved one (the trade's own when its decision is unchanged, else
     * the order's it fills) or a new check.
     *
     * @return array{kept: ?TierSnapshot, order: ?Order, check: ?array} 'order': the order whose reading is kept
     */
    public function readingFor(
        ?Trade $trade,
        ?Order $order,
        int $userId,
        string $symbol,
        string $action,
        string $timestamp,
        ?float $quantity = null,
        ?float $unitPrice = null
    ): array
    {
        $symbol = strtoupper(trim($symbol));
        $action = strtoupper($action);
        $day    = Carbon::parse($timestamp)->format('Y-m-d');

        $own = $trade?->tierSnapshot;
        if ($own !== null && $trade->symbol === $symbol && $trade->action === $action
            && $trade->timestamp?->format('Y-m-d') === $day
        ) {
            return ['kept' => $own, 'order' => $this->_orderSharing($own), 'check' => null];
        }
        $order ??= $trade?->linkedOrders()->with('tierSnapshot')->first();
        if ($order?->tierSnapshot !== null && $order->symbol === $symbol && $order->action === $action) {
            return ['kept' => $order->tierSnapshot, 'order' => $order, 'check' => null];
        }

        $check = $day === Carbon::today()->format('Y-m-d')
            ? $this->_live->live($userId, $symbol, $action, $quantity, $unitPrice)
            : $this->_rebuilt->check($userId, $symbol, $action, $day);

        return ['kept' => null, 'order' => null, 'check' => $check];
    }

    /**
     * Points the trade to its reading: the kept one, or a new row for a new check. The trade's previous row is retired
     * (soft-deleted) when nothing else points to it.
     */
    public function save(Trade $trade, array $reading): void
    {
        $previous = $trade->tierSnapshot;
        $snapshot = $reading['kept'];
        if ($snapshot === null && $reading['check'] !== null) {
            $snapshot = TierSnapshot::createFromCheck($trade->symbol, $trade->action, $reading['check'], false);
        }
        if ($snapshot === null || $snapshot->id === $trade->tier_snapshot_id) {
            return;
        }
        $trade->tier_snapshot_id = $snapshot->id;
        // Quietly: only the reading changes, which the performance caches do not depend on.
        $trade->saveQuietly();
        $trade->setRelation('tierSnapshot', $snapshot);
        $this->_retire($previous, $trade);
    }

    /**
     * A trade linked to an order: it takes the order's reading unless its own is a live one.
     */
    public function linkToOrder(Trade $trade, Order $order): void
    {
        $own = $trade->tierSnapshot;
        if ($order->tierSnapshot === null || $own?->source === TierSnapshot::SOURCE_LIVE
            || $order->symbol !== $trade->symbol || $order->action !== $trade->action
        ) {
            return;
        }
        $this->save($trade, ['kept' => $order->tierSnapshot, 'order' => $order, 'check' => null]);
    }

    /**
     * The order that saved this reading, when the trade shares it.
     */
    private function _orderSharing(TierSnapshot $snapshot): ?Order
    {
        return Order::where('tier_snapshot_id', $snapshot->id)->first();
    }

    private function _retire(?TierSnapshot $previous, Trade $trade): void
    {
        if ($previous === null || $previous->trashed()) {
            return;
        }
        $shared = Order::where('tier_snapshot_id', $previous->id)->exists()
            || Trade::where('tier_snapshot_id', $previous->id)->where('id', '!=', $trade->id)->exists();
        if (!$shared) {
            $previous->delete();
        }
    }
}

<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\Order;
use ovidiuro\myfinance2\App\Models\TierSnapshot;
use ovidiuro\myfinance2\App\Models\Trade;

/**
 * The tier reading saved with an order (TierSnapshot) through its life:
 *   - created: a live reading and its warnings; the serious ones confirmed on save;
 *   - edited, same symbol and action: the reading is kept and the warnings re-checked with the new quantity and limit
 *     price; a new row (same reading) only when the warning set changes, and a new serious warning asks again;
 *   - edited, symbol or action changed: a new decision, so a new live reading;
 *   - placed, filled, expired, cancelled: untouched.
 * An order created before readings were saved has none; editing it does not take one unless its symbol or action
 * changes.
 */
final class OrderTierSnapshots
{
    public function __construct(private readonly OrderTierCheck $_check)
    {
    }

    public static function make(): self
    {
        return new self(OrderTierCheck::make());
    }

    public function check(): OrderTierCheck
    {
        return $this->_check;
    }

    /**
     * The check an order form saves against: live for a new order or a changed decision, else the kept reading
     * re-checked. 'kept' is the order's snapshot when its reading is kept (null otherwise).
     *
     * @return array{check: ?array, kept: ?TierSnapshot}
     */
    public function checkFor(
        ?Order $order,
        int $userId,
        string $symbol,
        string $action,
        ?float $quantity,
        ?float $limitPrice
    ): array
    {
        $symbol = strtoupper(trim($symbol));
        $action = strtoupper($action);
        if ($order === null || $order->symbol !== $symbol || $order->action !== $action) {
            return ['check' => $this->_check->live($userId, $symbol, $action, $quantity, $limitPrice), 'kept' => null];
        }
        $kept = $order->tierSnapshot;
        if ($kept === null) {
            return ['check' => null, 'kept' => null];
        }
        $check = $this->_check->evaluate(
            $kept->reading, $action, $quantity, $limitPrice, $this->_check->staleAge($userId, $symbol)
        ) + ['source' => $kept->source, 'taken_at' => $kept->taken_at?->format('Y-m-d H:i:s'),
            'as_of' => $kept->as_of_date?->format('Y-m-d')];

        return ['check' => $check, 'kept' => $kept];
    }

    /**
     * Today's tier in use and serious warnings of each open order with a saved reading (the order list's "now" chip),
     * all from the one live reading of the user.
     *
     * @param iterable<Order> $orders
     *
     * @return array<int, array{tier: ?string, serious: array}> order id => today's reading
     */
    public function nowFor(iterable $orders, int $userId): array
    {
        $now = [];
        foreach ($orders as $order) {
            if (!$order->isOpen() || $order->tierSnapshot === null) {
                continue;
            }
            $check = $this->_check->live(
                $userId, $order->symbol, $order->action,
                $order->quantity !== null ? (float) $order->quantity : null,
                $order->limit_price !== null ? (float) $order->limit_price : null
            );
            $now[$order->id] = [
                'tier'    => $check['reading']['effective_tier'] ?? null,
                'serious' => OrderTierWarnings::serious($check['warnings']),
            ];
        }

        return $now;
    }

    /**
     * The serious codes of a check still to be confirmed: not in $acknowledged (what the confirmation showed), nor
     * confirmed earlier on the kept reading.
     *
     * @return string[]
     */
    public static function missing(?array $check, array $acknowledged, ?TierSnapshot $kept): array
    {
        if ($check === null) {
            return [];
        }
        $earlier = $kept !== null && $kept->acknowledged_at !== null ? array_column($kept->serious(), 'code') : [];

        return OrderTierWarnings::unacknowledged($check['warnings'], array_merge($acknowledged, $earlier));
    }

    /**
     * Saves the check with the order: a new row for a new reading or a changed warning set (the old one
     * soft-deleted), nothing when the kept reading raises the same warnings.
     */
    public function save(Order $order, ?array $check, ?TierSnapshot $kept): void
    {
        if ($check === null
            || ($kept !== null && OrderTierWarnings::sameSet($kept->warnings ?? [], $check['warnings']))
        ) {
            return;
        }
        $previous = $order->tierSnapshot;
        $snapshot = TierSnapshot::createFromCheck($order->symbol, $order->action, $check, true);
        $order->tier_snapshot_id = $snapshot->id;
        $order->save();
        $order->setRelation('tierSnapshot', $snapshot);
        // Retired, unless the trade that filled the order still shows it (the decision it was made under).
        if ($previous !== null && !Trade::where('tier_snapshot_id', $previous->id)->exists()) {
            $previous->delete();
        }
    }
}

<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * The tier check of an order: a tier reading (live from LiveTierReadings, or the one saved with the order) and the
 * warnings it raises for the order's action, quantity and limit price (OrderTierWarnings). The order's share of the
 * portfolio after the order and the quote's staleness are the only order-dependent facts, added here.
 *
 * A check: source, reading (with its 'facts' and 'position'), warnings, verdict, position_pct_after, taken_at, as_of.
 */
final class OrderTierCheck
{
    public function __construct(
        private readonly LiveTierReadings $_live,
        private readonly OrderTierWarnings $_warnings
    )
    {
    }

    public static function make(): self
    {
        return new self(app(LiveTierReadings::class), OrderTierWarnings::fromConfig());
    }

    /**
     * A new decision: today's reading of the symbol.
     */
    public function live(int $userId, string $symbol, string $action, ?float $quantity, ?float $limitPrice): array
    {
        $all     = $this->_live->forUser($userId);
        $entry   = $all['symbols'][$symbol] ?? null;
        $reading = $entry === null
            ? TierReadingBuilder::unrated($symbol, $symbol . ' is not on the watchlist: no tier reading.')
                + ['facts' => ['has_reading' => false, 'symbol' => $symbol], 'position' => null]
            : $entry['reading'] + ['facts' => $entry['facts'], 'position' => $entry['position']];

        return ['source' => 'live', 'taken_at' => $all['taken_at'], 'as_of' => $all['as_of']]
            + $this->evaluate($reading, $action, $quantity, $limitPrice, $all['stale'][$symbol] ?? null);
    }

    /**
     * The warnings of a reading (live or saved) for an order.
     *
     * @return array{reading: array, warnings: array, verdict: string, position_pct_after: ?float}
     */
    public function evaluate(
        array $reading,
        string $action,
        ?float $quantity,
        ?float $limitPrice,
        ?string $staleAge
    ): array
    {
        $after    = self::positionAfter($reading['position'] ?? null, $action, $quantity, $limitPrice);
        $facts    = ['position_pct_after' => $after, 'stale_quote_age' => $staleAge] + ($reading['facts'] ?? []);
        $warnings = $this->_warnings->evaluate($action, $facts);

        return [
            'reading'            => $reading,
            'warnings'           => $warnings,
            'verdict'            => OrderTierWarnings::verdict($warnings),
            'position_pct_after' => $after,
        ];
    }

    /**
     * Whether today's live reading of the symbol is stale, for a re-check of a saved reading.
     */
    public function staleAge(int $userId, string $symbol): ?string
    {
        return $this->_live->staleAge($userId, $symbol);
    }

    /**
     * The symbol's share of the portfolio (by market value, in EUR) once the order fills: the order's amount added
     * (BUY) or taken off (SELL) at the limit price, else today's price. Null without a quantity, a price, an EUR rate
     * or a portfolio.
     *
     * @param array|null $position mvalue_eur, total_mvalue_eur, price_native, eur_rate (LiveTierReadings)
     */
    public static function positionAfter(?array $position, string $action, ?float $quantity, ?float $limitPrice): ?float
    {
        $total = $position['total_mvalue_eur'] ?? null;
        $price = $limitPrice !== null && $limitPrice > 0 ? $limitPrice : ($position['price_native'] ?? null);
        $rate  = $position['eur_rate'] ?? null;
        if ($total === null || $quantity === null || $quantity <= 0 || $price === null || $rate === null) {
            return null;
        }
        $amount = $quantity * $price * $rate;
        $sign   = strtoupper($action) === 'SELL' ? -1.0 : 1.0;
        $value  = max(0.0, (float) $position['mvalue_eur'] + $sign * $amount);
        $after  = $total + $sign * $amount;

        return $after > 0.0 ? round($value / $after * 100.0, 1) : null;
    }
}

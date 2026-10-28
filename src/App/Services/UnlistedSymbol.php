<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * Helpers for unlisted symbols — symbols that have no exchange listing and
 * whose prices are provided via the myfinance2.trades.unlisted_fmv config entry.
 *
 * All methods are stateless and static; no DB or API calls are made.
 */
class UnlistedSymbol
{
    /**
     * Return true when the symbol has the configured unlisted prefix
     * (e.g. "UNLISTED_MIRO").
     */
    public static function isUnlisted(string $symbol): bool
    {
        return str_starts_with($symbol, config('myfinance2.trades.unlisted'));
    }

    /**
     * The symbol split for display: the unlisted prefix with its separator (e.g. "UNLISTED_", shown smaller to
     * save width) and the rest ("ACME"). A listed symbol has an empty prefix.
     *
     * @return array{prefix: string, name: string}
     */
    public static function labelParts(string $symbol): array
    {
        $prefix = (string) config('myfinance2.trades.unlisted');
        if ($prefix === '' || !str_starts_with($symbol, $prefix) || $symbol === $prefix) {
            return ['prefix' => '', 'name' => $symbol];
        }

        // The separator after the prefix ("UNLISTED" + "_") belongs to the small part.
        $name      = substr($symbol, strlen($prefix));
        $separator = strlen($name) - strlen(ltrim($name, '_-'));
        if ($separator === strlen($name)) {
            return ['prefix' => '', 'name' => $symbol];
        }

        return ['prefix' => $prefix . substr($name, 0, $separator), 'name' => substr($name, $separator)];
    }

    /**
     * Look up the FMV price and its timestamp for an unlisted symbol on or
     * before $date, using the raw FMV data array from config.
     *
     * Returns the most recent quote whose timestamp is ≤ $date.
     * Returns [0, $date] when no quotes are present.
     *
     * @param  array<array{price: float, timestamp: string}>  $fmvData
     * @return array{float, \DateTime}
     */
    public static function getPriceAndTimestamp(
        array $fmvData,
        \DateTimeInterface $date = null
    ): array
    {
        if (empty($date)) {
            $date = new \DateTime();
        }

        $price = 0;
        $priceTimestamp = $date;

        if (empty($fmvData['quotes'])) {
            return [$price, $priceTimestamp];
        }

        $price = $fmvData['quotes'][0]['price'];
        $priceTimestamp = new \DateTime($fmvData['quotes'][0]['timestamp']);

        foreach ($fmvData['quotes'] as $quote) {
            $quoteTimestamp = new \DateTime($quote['timestamp']);
            if ($quoteTimestamp <= $date && $quoteTimestamp > $priceTimestamp) {
                $price = $quote['price'];
                $priceTimestamp = $quoteTimestamp;
            }
        }

        return [$price, $priceTimestamp];
    }

    /**
     * Convenience wrapper: look up the FMV price for a symbol by name,
     * reading the full config internally.
     * Returns null when the symbol is not found in config or has no quotes.
     */
    public static function getPrice(string $symbol, \DateTimeInterface $date): ?float
    {
        $fmvData = config('myfinance2.trades.unlisted_fmv');
        if (empty($fmvData[$symbol])) {
            return null;
        }
        [$price] = self::getPriceAndTimestamp($fmvData[$symbol], $date);
        return $price > 0 ? $price : null;
    }

    /**
     * Return the display name for an unlisted symbol.
     * Falls back to the symbol itself when no name is configured.
     */
    public static function getName(string $symbol): string
    {
        $fmvData = config('myfinance2.trades.unlisted_fmv');
        return $fmvData[$symbol]['symbol_name'] ?? $symbol;
    }
}

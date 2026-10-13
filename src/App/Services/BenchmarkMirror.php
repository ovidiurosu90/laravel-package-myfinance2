<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * The same trades, made in the benchmark (VUSA.AS) instead: every buy puts the same amount into the
 * benchmark on the same day, every sell takes out the same share of the position on the same day (selling
 * 60 of 160 shares sells 37.5 % of the benchmark units). Timing and sizing are identical, so comparing the
 * two returns isolates the choice of stock. The benchmark prices must be in the stock's trade currency;
 * the return is gain over invested, like the holding's own return.
 */
final class BenchmarkMirror
{
    private array $_dates;

    /**
     * @param array<string, float> $_prices date => benchmark close in the stock's currency, ascending
     */
    public function __construct(private readonly array $_prices)
    {
        $this->_dates = array_keys($_prices);
    }

    /**
     * Return of the mirrored trades as of $date: (proceeds of mirrored sells + value of the units still
     * held - amount invested) / amount invested, in %. Null without a benchmark price for a trade or $date.
     *
     * @param array $trades one holding period's trades, ascending: ['date', 'action', 'quantity', 'price']
     */
    public function returnOn(array $trades, string $date): ?float
    {
        $units    = 0.0;
        $held     = 0.0;
        $invested = 0.0;
        $proceeds = 0.0;
        foreach ($trades as $trade) {
            if ($trade['date'] > $date) {
                break;
            }
            $price = $this->priceOn($trade['date']);
            if ($price === null || $price <= 0.0) {
                return null;
            }
            if ($trade['action'] === 'BUY') {
                $amount    = $trade['quantity'] * $trade['price'];
                $invested += $amount;
                $units    += $amount / $price;
                $held     += $trade['quantity'];
            } elseif ($trade['action'] === 'SELL' && $held > 0.0) {
                $share     = min(1.0, $trade['quantity'] / $held);
                $proceeds += $units * $share * $price;
                $units    -= $units * $share;
                $held     -= min($held, $trade['quantity']);
            }
        }
        $now = $this->priceOn($date);
        if ($invested <= 0.0 || $now === null) {
            return null;
        }

        return ($proceeds + $units * $now - $invested) / $invested * 100.0;
    }

    public function priceOn(string $date): ?float
    {
        return StoredCloses::onOrBefore($this->_prices, $this->_dates, $date);
    }
}

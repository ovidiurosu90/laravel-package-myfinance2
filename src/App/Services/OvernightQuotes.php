<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;
use Scheb\YahooFinanceApi\Results\Quote;

/**
 * Adds the overnight session's price to US quotes, without losing any other session's.
 *
 * Yahoo sends the overnight fields (US stocks, 20:00 to 04:00 New York time) only to a quote request flagged with
 * overnightPrice=true, and a flagged response leaves out the after-hours fields. So the quotes are always fetched
 * unflagged (regular, pre and after-hours prices, any exchange) and, only while the overnight session runs, the US
 * ones once more flagged, whose overnight fields are added to them. Every quote then carries every session's latest
 * price, and OvernightSession::latest() picks the newest: the overnight one when a symbol trades overnight, else its
 * after-hours price as before.
 */
final class OvernightQuotes
{
    /** The exchanges with an overnight session: the US ones. */
    private const US_TIMEZONE = 'America/New_York';

    /**
     * @param Quote[]                     $quotes       the unflagged quotes
     * @param callable(string[]): Quote[] $fetchFlagged fetches the flagged quotes of the given symbols
     * @param \DateTimeInterface|null     $now          the time that decides whether the session runs (now)
     *
     * @return Quote[] the quotes, in the same order, the US ones with their overnight fields when Yahoo sent them
     */
    public static function add(array $quotes, callable $fetchFlagged, ?\DateTimeInterface $now = null): array
    {
        if (!OvernightSession::isOpen($now)) {
            return $quotes;
        }

        $symbols = [];
        foreach ($quotes as $quote) {
            if ($quote instanceof LiveQuote && $quote->getExchangeTimezoneName() === self::US_TIMEZONE) {
                $symbols[] = (string) $quote->getSymbol();
            }
        }
        if (empty($symbols)) {
            return $quotes;
        }

        try {
            $flagged = $fetchFlagged($symbols);
        } catch (\Throwable $e) {
            // The unflagged quotes are complete without it: only the overnight price is missing until the next fetch.
            Log::info('OvernightQuotes: no overnight prices for ' . implode(', ', $symbols) . ': ' . $e->getMessage());
            return $quotes;
        }

        $bySymbol = [];
        foreach ($flagged as $quote) {
            if ($quote instanceof LiveQuote) {
                $bySymbol[(string) $quote->getSymbol()] = $quote;
            }
        }

        return array_map(
            fn (Quote $quote): Quote => $quote instanceof LiveQuote && isset($bySymbol[(string) $quote->getSymbol()])
                ? $quote->withOvernightOf($bySymbol[(string) $quote->getSymbol()])
                : $quote,
            $quotes
        );
    }
}

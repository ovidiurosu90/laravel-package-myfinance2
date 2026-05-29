<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Caches historical close prices fetched from Yahoo Finance for a symbol+year.
 * Used by DrawdownService to fill gaps where stats_historical has no data.
 * Prices are stored in native currency; the caller applies EUR conversion.
 */
class HistoricalPriceCache
{
    private const CACHE_KEY_PREFIX = 'hist_price_v1_';
    private const CACHE_TTL_HIT    = 604800; // 7 days — stable past data
    private const CACHE_TTL_MISS   = 3600;   // 1 hour — retry transient failures

    /**
     * @return array{currency: string|null, prices: array<string, float>}
     */
    public function fetch(string $symbol, string $fromDate, string $toDate): array
    {
        if (FinanceAPI::isSkippedSymbol($symbol)) {
            return ['currency' => null, 'prices' => []];
        }

        // Round fromDate back to year start to maximise cache reuse across days.
        $fromYear = substr($fromDate, 0, 4);
        $cacheKey = self::CACHE_KEY_PREFIX
            . $symbol . '_' . $fromYear . '_' . substr($toDate, 0, 7);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->_fetchFromApi($symbol, $fromYear . '-01-01', $toDate);

        $ttl = !empty($result['prices']) ? self::CACHE_TTL_HIT : self::CACHE_TTL_MISS;
        Cache::put($cacheKey, $result, $ttl);

        return $result;
    }

    /**
     * @return array{currency: string|null, prices: array<string, float>}
     */
    private function _fetchFromApi(string $symbol, string $fromDate, string $toDate): array
    {
        $empty = ['currency' => null, 'prices' => []];

        try {
            $financeAPI = new FinanceAPI();
            $quote      = $financeAPI->getQuote($symbol, true, false); // no DB persist
            if (empty($quote)) {
                return $empty;
            }

            $historicalData = $financeAPI->getHistoricalPeriodQuoteData(
                $quote,
                new \DateTime($fromDate),
                new \DateTime($toDate)
            );

            if (empty($historicalData)) {
                return ['currency' => $quote->getCurrency(), 'prices' => []];
            }

            $prices = [];
            foreach ($historicalData as $item) {
                $close = $item->getClose();
                if ($close !== null && $close > 0.0) {
                    $prices[$item->getDate()->format('Y-m-d')] = (float) $close;
                }
            }

            Log::info(
                "HistoricalPriceCache: fetched " . count($prices)
                . " prices for $symbol ($fromDate to $toDate)"
            );

            return ['currency' => $quote->getCurrency(), 'prices' => $prices];
        } catch (\Exception $e) {
            Log::warning(
                "HistoricalPriceCache: failed to fetch $symbol "
                . "($fromDate to $toDate): " . $e->getMessage()
            );
            return $empty;
        }
    }
}

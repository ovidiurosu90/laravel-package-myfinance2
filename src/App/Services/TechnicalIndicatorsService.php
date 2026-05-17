<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;
use ovidiuro\myfinance2\App\Models\StatHistorical;

class TechnicalIndicatorsService
{
    private const RSI_PERIOD = 14;
    private const ANALYST_CACHE_TTL = 86400; // 24 hours
    private const ANALYST_CACHE_PREFIX = 'ANALYST_TARGET_';
    private const ANALYST_CONCURRENCY = 5;
    private const ANALYST_TIMEOUT = 4;
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
        . 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36';

    /**
     * Attach technical indicators to all items.
     *
     * Adds a 'technical_indicators' key to each item with:
     *   analyst_target_price, analyst_target_delta_pct, analyst_opinions_count,
     *   rsi, ma50, ma200, ma50_diff_pct, ma200_diff_pct
     *
     * @param array $items (symbol => quoteData)
     * @return array
     */
    public function attachIndicators(array $items): array
    {
        $symbols = array_keys($items);
        $rsiBySymbol = $this->_computeRsiForSymbols($symbols);
        $analystBySymbol = $this->_fetchAllAnalystData($symbols);

        foreach ($items as $symbol => $quoteData) {
            $currentPrice = isset($quoteData['price']) ? (float) $quoteData['price'] : null;

            $ma50DiffPct = isset($quoteData['fiftyDayAverageChangePercent'])
                ? (float) $quoteData['fiftyDayAverageChangePercent'] * 100
                : null;
            $ma200DiffPct = isset($quoteData['twoHundredDayAverageChangePercent'])
                ? (float) $quoteData['twoHundredDayAverageChangePercent'] * 100
                : null;

            $analystData = $analystBySymbol[$symbol] ?? [];
            $analystTargetPrice = $analystData['targetMeanPrice'] ?? null;
            $analystTargetDeltaPct = null;
            if ($analystTargetPrice !== null && $currentPrice !== null && $currentPrice > 0) {
                $analystTargetDeltaPct = (($analystTargetPrice - $currentPrice) / $currentPrice) * 100;
            }

            $rsi = $rsiBySymbol[$symbol] ?? null;

            $items[$symbol]['technical_indicators'] = [
                'analyst_target_price'     => $analystTargetPrice,
                'analyst_target_delta_pct' => $analystTargetDeltaPct,
                'analyst_opinions_count'   => $analystData['numberOfAnalystOpinions'] ?? null,
                'analyst_target_high'      => $analystData['targetHighPrice'] ?? null,
                'analyst_target_low'       => $analystData['targetLowPrice'] ?? null,
                'rsi'                      => $rsi,
                'ma50'                     => $quoteData['fiftyDayAverage'] ?? null,
                'ma200'                    => $quoteData['twoHundredDayAverage'] ?? null,
                'ma50_diff_pct'            => $ma50DiffPct,
                'ma200_diff_pct'           => $ma200DiffPct,
                'signals'                  => $this->_computeSignals(
                    $analystTargetDeltaPct, $rsi, $ma50DiffPct, $ma200DiffPct
                ),
            ];
        }

        return $items;
    }

    /**
     * Derive buy / hold / sell signals from each indicator.
     * null means no clear signal can be drawn from that metric alone.
     *
     * @return array{analyst: string|null, rsi: string|null, ma50: string|null, ma200: string|null}
     */
    private function _computeSignals(
        ?float $analystDeltaPct,
        ?float $rsi,
        ?float $ma50DiffPct,
        ?float $ma200DiffPct
    ): array
    {
        // Analyst: always actionable when we have a target
        $analystSignal = null;
        if ($analystDeltaPct !== null) {
            if ($analystDeltaPct >= 15)     { $analystSignal = 'buy'; }
            elseif ($analystDeltaPct >= 0)  { $analystSignal = 'hold'; }
            else                            { $analystSignal = 'sell'; }
        }

        // RSI: only at confirmed extremes
        $rsiSignal = null;
        if ($rsi !== null) {
            if ($rsi < 30)      { $rsiSignal = 'buy'; }
            elseif ($rsi > 70)  { $rsiSignal = 'sell'; }
        }

        // MA50/MA200: only flag sell when price is below the average
        $ma50Signal  = ($ma50DiffPct  !== null && $ma50DiffPct  < 0) ? 'sell' : null;
        $ma200Signal = ($ma200DiffPct !== null && $ma200DiffPct < 0) ? 'sell' : null;

        return [
            'analyst' => $analystSignal,
            'rsi'     => $rsiSignal,
            'ma50'    => $ma50Signal,
            'ma200'   => $ma200Signal,
        ];
    }

    private function _computeRsiForSymbols(array $symbols): array
    {
        $rows = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
            ->whereIn('symbol', $symbols)
            ->orderBy('date', 'asc')
            ->select(['symbol', 'unit_price'])
            ->get();

        $pricesBySymbol = [];
        foreach ($rows as $row) {
            $pricesBySymbol[$row->symbol][] = (float) $row->unit_price;
        }

        $rsiBySymbol = [];
        foreach ($pricesBySymbol as $symbol => $prices) {
            $rsi = $this->_computeRsi($prices, self::RSI_PERIOD);
            if ($rsi !== null) {
                $rsiBySymbol[$symbol] = $rsi;
            }
        }

        return $rsiBySymbol;
    }

    /**
     * Wilder's RSI using the full price series for smoothing stability.
     */
    private function _computeRsi(array $prices, int $period): ?float
    {
        if (count($prices) < $period + 1) {
            return null;
        }

        $changes = [];
        for ($i = 1, $count = count($prices); $i < $count; $i++) {
            $changes[] = $prices[$i] - $prices[$i - 1];
        }

        $firstGains = array_map(fn($c) => $c > 0 ? $c : 0.0, array_slice($changes, 0, $period));
        $firstLosses = array_map(fn($c) => $c < 0 ? abs($c) : 0.0, array_slice($changes, 0, $period));

        $avgGain = array_sum($firstGains) / $period;
        $avgLoss = array_sum($firstLosses) / $period;

        foreach (array_slice($changes, $period) as $change) {
            $gain = $change > 0 ? $change : 0.0;
            $loss = $change < 0 ? abs($change) : 0.0;
            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;
        }

        if ($avgLoss === 0.0) {
            return 100.0;
        }

        return round(100.0 - (100.0 / (1.0 + ($avgGain / $avgLoss))), 1);
    }

    private function _fetchAllAnalystData(array $symbols): array
    {
        $results = [];
        $toFetch = [];

        foreach ($symbols as $symbol) {
            $cached = Cache::get(self::ANALYST_CACHE_PREFIX . $symbol);
            if ($cached !== null) {
                $results[$symbol] = $cached;
            } else {
                $toFetch[] = $symbol;
            }
        }

        if (empty($toFetch)) {
            return $results;
        }

        $fetched = $this->_fetchConcurrent($toFetch);
        foreach ($fetched as $symbol => $data) {
            Cache::put(self::ANALYST_CACHE_PREFIX . $symbol, $data, self::ANALYST_CACHE_TTL);
            $results[$symbol] = $data;
        }

        return $results;
    }

    private function _fetchConcurrent(array $symbols): array
    {
        $client = new Client([
            'timeout'         => self::ANALYST_TIMEOUT,
            'connect_timeout' => 3,
        ]);

        $results = [];

        $requests = function () use ($symbols) {
            foreach ($symbols as $symbol) {
                $url = 'https://query2.finance.yahoo.com/v10/finance/quoteSummary/'
                    . urlencode($symbol) . '?modules=financialData';
                yield $symbol => new GuzzleRequest('GET', $url, [
                    'User-Agent' => self::USER_AGENT,
                ]);
            }
        };

        $pool = new Pool($client, $requests(), [
            'concurrency' => self::ANALYST_CONCURRENCY,
            'fulfilled' => function ($response, string $symbol) use (&$results) {
                $results[$symbol] = $this->_parseAnalystResponse(
                    (string) $response->getBody()
                );
            },
            'rejected' => function ($reason, string $symbol) use (&$results) {
                Log::info("TechnicalIndicatorsService: no analyst data for {$symbol}");
                $results[$symbol] = [];
            },
        ]);

        $pool->promise()->wait();

        return $results;
    }

    private function _parseAnalystResponse(string $body): array
    {
        $decoded = json_decode($body, true);
        $fd = $decoded['quoteSummary']['result'][0]['financialData'] ?? [];

        return [
            'targetMeanPrice'         => $fd['targetMeanPrice']['raw'] ?? null,
            'targetHighPrice'         => $fd['targetHighPrice']['raw'] ?? null,
            'targetLowPrice'          => $fd['targetLowPrice']['raw'] ?? null,
            'numberOfAnalystOpinions' => $fd['numberOfAnalystOpinions']['raw'] ?? null,
        ];
    }
}

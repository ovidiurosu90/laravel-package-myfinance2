<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Detects stale live-quote data for the pages that render live positions
 * (/positions, /watchlist-symbols, /overview).
 *
 * The incident this guards against: for a few hours the app kept showing unchanged US prices
 * while the US market was open and Yahoo's own site had fresh prices, and nothing was logged.
 * Yahoo was returning a quote whose regularMarketTime had stopped advancing, so the app kept
 * rendering an old price with no visible signal that it was frozen.
 *
 * Detection is per market, not per symbol, to avoid alert spam:
 *   1. Consider only markets that should be open right now (schedule-based, so a frozen quote's
 *      own market-state field cannot hide the problem).
 *   2. For each such market take the freshest regular-market price timestamp across its symbols.
 *   3. If even that freshest timestamp is older than the threshold, the market's feed is stale
 *      and one alert is raised for the whole market.
 *
 * Using the freshest timestamp (not each symbol's own) also avoids false positives from a single
 * thinly traded symbol that merely had no recent trade: a healthy market always has at least one
 * liquid symbol printing within the last minute.
 */
class StaleQuoteService
{
    // Fallback when config is unavailable. The configured value (myfinance2.stale_quote) wins.
    private const _DEFAULT_THRESHOLD_SECONDS = 600; // 10 minutes

    // Friendly market labels keyed by MarketUtils::getMarketName().
    private const _MARKET_LABELS = [
        'NYSE' => 'US market (NYSE / Nasdaq)',
        'XPAR' => 'Euronext Paris',
        'XAMS' => 'Euronext Amsterdam',
        'LSE'  => 'London Stock Exchange',
    ];

    /**
     * Build the stale-market alerts for a quotes map (symbol => quote array, as produced by
     * FinanceUtils::getQuotes()). Never throws: this is a display-time safety net and must never
     * break the page, so any failure is logged and an empty list is returned.
     *
     * @param array $quotes            symbol => quote array (must carry 'marketUtils' and a
     *                                 regular-market timestamp to be considered)
     * @param int|null $thresholdSeconds override for the configured staleness threshold
     * @return array<int, array<string, mixed>> one entry per stale market, most stale first
     */
    public function detect(array $quotes, ?int $thresholdSeconds = null): array
    {
        try {
            return $this->_detect($quotes, $thresholdSeconds);
        } catch (\Throwable $e) {
            Log::warning('StaleQuoteService skipped: ' . $e->getMessage());
            return [];
        }
    }

    private function _detect(array $quotes, ?int $thresholdSeconds): array
    {
        if (!config('myfinance2.stale_quote.enabled', true)) {
            return [];
        }

        $threshold = $thresholdSeconds ?? (int) config(
            'myfinance2.stale_quote.threshold_seconds',
            self::_DEFAULT_THRESHOLD_SECONDS
        );
        if ($threshold <= 0) {
            return [];
        }

        $byMarket = $this->_groupOpenMarkets($quotes);

        $now    = time();
        $fmt    = trans('myfinance2::general.datetime-format');
        $alerts = [];
        foreach ($byMarket as $key => $market) {
            $ageSeconds = $now - $market['latest']->getTimestamp();
            if ($ageSeconds < $threshold) {
                continue;
            }

            sort($market['symbols']);
            $alerts[] = [
                'market_key'            => $key,
                'market_label'          => $market['label'],
                'symbols'               => $market['symbols'],
                'symbol_count'          => count($market['symbols']),
                'last_update'           => $market['latest'],
                'last_update_formatted' => $market['latest']->format($fmt),
                'age_seconds'           => $ageSeconds,
                'age_human'             => self::humanizeDuration($ageSeconds),
                'threshold_seconds'     => $threshold,
                'threshold_human'       => self::humanizeDuration($threshold),
            ];
        }

        // Most stale first.
        usort($alerts, static fn ($a, $b) => $b['age_seconds'] <=> $a['age_seconds']);

        return $alerts;
    }

    /**
     * Group the open markets and track, per market, the freshest regular-market timestamp and the
     * symbols seen. Symbols without a live quote (unlisted / missing) or whose market is not open
     * right now are skipped.
     *
     * @return array<string, array{label: string, symbols: array<int, string>, latest: \DateTimeInterface}>
     */
    private function _groupOpenMarkets(array $quotes): array
    {
        $byMarket = [];
        foreach ($quotes as $symbol => $quote) {
            if (!is_array($quote) || empty($quote['marketUtils'])) {
                continue;
            }
            $marketUtils = $quote['marketUtils'];
            if (!($marketUtils instanceof MarketUtils) || !$this->_marketIsOpen($marketUtils)) {
                continue;
            }

            $timestamp = $this->_regularTimestamp($quote);
            if (!($timestamp instanceof \DateTimeInterface)) {
                continue;
            }

            $key = $this->_marketKey($marketUtils);
            if (empty($byMarket[$key])) {
                $byMarket[$key] = [
                    'label'   => $this->_marketLabel($marketUtils),
                    'symbols' => [],
                    'latest'  => $timestamp,
                ];
            }
            $byMarket[$key]['symbols'][] = (string) $symbol;
            if ($timestamp->getTimestamp() > $byMarket[$key]['latest']->getTimestamp()) {
                $byMarket[$key]['latest'] = $timestamp;
            }
        }

        return $byMarket;
    }

    /**
     * Whether the symbol's market should be open right now. Prefers the schedule-based status
     * (MarketUtils::getMarketStatus(), cached per market) so a frozen quote's own market-state
     * field cannot mask a stale feed. Falls back to the quote's market state when the schedule is
     * unknown (e.g. crypto, which has no exchange session) or the status lookup fails.
     */
    private function _marketIsOpen(MarketUtils $marketUtils): bool
    {
        try {
            $status = $marketUtils->getMarketStatus()['status'] ?? 'UNKNOWN';
            if ($status === 'OPEN') {
                return true;
            }
            if ($status === 'CLOSED') {
                return false;
            }
        } catch (\Throwable $e) {
            // Fall through to the quote's own market state below.
        }

        return $marketUtils->isOpen();
    }

    /**
     * The regular-session price timestamp for a quote. The regular-market timestamp is preferred
     * over the generic quote timestamp, which pre/post-market values can overwrite; both are
     * \DateTime in Europe/Amsterdam.
     */
    private function _regularTimestamp(array $quote): ?\DateTimeInterface
    {
        $timestamp = $quote['regular_market_timestamp'] ?? $quote['quote_timestamp'] ?? null;

        return $timestamp instanceof \DateTimeInterface ? $timestamp : null;
    }

    /**
     * Grouping key for a market. NYSE and NasdaqGS collapse to a single US bucket via
     * getMarketName(); other exchanges fall back to their exchange or Yahoo market name.
     */
    private function _marketKey(MarketUtils $marketUtils): string
    {
        $name = (string) $marketUtils->getMarketName();
        if ($name !== '') {
            return $name;
        }
        $exchange = (string) $marketUtils->getExchangeName();
        if ($exchange !== '') {
            return $exchange;
        }

        return (string) $marketUtils->getName() ?: 'UNKNOWN';
    }

    private function _marketLabel(MarketUtils $marketUtils): string
    {
        $key = $this->_marketKey($marketUtils);
        if (isset(self::_MARKET_LABELS[$key])) {
            return self::_MARKET_LABELS[$key];
        }

        $exchange = (string) $marketUtils->getExchangeName();

        return $exchange !== '' ? $exchange : $key;
    }

    /**
     * Compact human-readable duration, e.g. 45 => "45s", 634 => "10m 34s", 7320 => "2h 2m".
     */
    public static function humanizeDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }

        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            $remSeconds = $seconds % 60;
            return $remSeconds > 0 ? "{$minutes}m {$remSeconds}s" : "{$minutes}m";
        }

        $hours      = intdiv($minutes, 60);
        $remMinutes = $minutes % 60;

        return $remMinutes > 0 ? "{$hours}h {$remMinutes}m" : "{$hours}h";
    }
}

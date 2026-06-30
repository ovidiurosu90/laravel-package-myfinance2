<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;
use Scheb\YahooFinanceApi\ApiClient;
use Scheb\YahooFinanceApi\Results\Quote;
use Scheb\YahooFinanceApi\Results\HistoricalData;
use ovidiuro\myfinance2\App\Models\Trade;

class FinanceUtils
{
    /**
     * Minimum number of daily closes required inside the trailing-365-day window before a
     * closing-based 52-week range is reported. Mirrors DrawdownService::MIN_HISTORY_DAYS so the
     * watchlist table (DrawdownService::_computeClosingExtremes) and the chart range bar
     * (closingExtremesNative) agree on whether a symbol has enough history to show a range. Keep
     * the two values in sync.
     */
    private const MIN_CLOSING_HISTORY_DAYS = 30;

    /**
     * @param string $symbol
     * @param string|null $timestamp
     *
     * @return array(price, currency, name, quote_timestamp) or null if failure
     */
    public function getFinanceDataBySymbol(string $symbol, ?string $timestamp = null): ?array
    {
        $financeAPI = new FinanceAPI();
        $quote = $financeAPI->getQuote($symbol);
        if (empty($quote)) {
            return null;
        }

        // LOG::debug('quote'); LOG::debug(var_export($quote, true));
        $price = $quote->getRegularMarketPrice();
        $currency = $quote->getCurrency();
        $name = $quote->getLongName();
        $quoteTimestamp = $quote->getRegularMarketTime();

        // The current (last) price reflects pre/post-market when the live quote carries it, so
        // surfaces that show a single current value (the range-bar marker) match the live price in
        // the quote header instead of lagging on the regular close. For a historical lookup the
        // close is the only meaningful "current" value.
        $currentPrice = self::_effectiveCurrentPrice($quote, $price);

        if (!empty($timestamp) && // Has timestamp
            date('Ymd') > date('Ymd', strtotime($timestamp)) // in the past
        ) {
            $historicalData = $financeAPI->getHistoricalQuoteData(
                $quote, new \DateTime($timestamp));

            if (empty($historicalData)) {
                return null;
            }

            // LOG::debug('historicalData');
            // LOG::debug(var_export($historicalData, true));
            $price = $historicalData->getClose();
            $quoteTimestamp = $historicalData->getDate();
            $currentPrice = $price;
        }

        /*
        $offset = self::get_timezone_offset(
            $quoteTimestamp->getTimezone()->getName());
        $quoteTimestamp->add(
            \DateInterval::createFromDateString((string)$offset . 'seconds'));
        // LOG::debug('offset'); LOG::debug(var_export($offset, true));
        */
        self::fixTimezone($quote, $quoteTimestamp);

        // Closing-based 52-week range (highest / lowest daily close over the past year, native
        // currency, dated) so the chart modal can show it as the primary range alongside Yahoo's
        // intraday high / low. Null fields when there is no usable history.
        $livePrice = is_numeric($currentPrice) ? (float) $currentPrice : null;
        $closing = $this->closingExtremesNative($symbol, $livePrice);

        return [
            'price'           => $price,
            'current_price'   => $currentPrice,
            'currency'        => $currency,
            'name'            => $name,
            'quote_timestamp' => $quoteTimestamp,

            'fiftyTwoWeekHigh'              => $quote->getFiftyTwoWeekHigh(),
            'fiftyTwoWeekHighChangePercent' =>
                $quote->getFiftyTwoWeekHighChangePercent(),
            'fiftyTwoWeekLow'               => $quote->getFiftyTwoWeekLow(),
            'fiftyTwoWeekLowChangePercent'  =>
                $quote->getFiftyTwoWeekLowChangePercent(),

            'closingHigh'     => $closing['high'] ?? null,
            'closingHighDate' => $closing['high_date'] ?? null,
            'closingLow'      => $closing['low'] ?? null,
            'closingLowDate'  => $closing['low_date'] ?? null,
        ];
    }

    /**
     * Effective "current" price for a live quote: the pre or post-market value when Yahoo provides
     * one, otherwise the regular-session price. Mirrors the pre/post overwrite getQuotes() applies,
     * so a surface showing a single current price (e.g. the 52-week range-bar marker) matches the
     * live price in the quote header rather than lagging on the regular close. Pre and post-market
     * never overlap; pre takes precedence to match getQuotes().
     *
     * @param mixed $regularPrice Regular-session price to fall back to.
     * @return mixed
     */
    private static function _effectiveCurrentPrice(Quote $quote, $regularPrice)
    {
        if (!empty($quote->getPreMarketPrice())) {
            return $quote->getPreMarketPrice();
        }
        if (!empty($quote->getPostMarketPrice())) {
            return $quote->getPostMarketPrice();
        }
        return $regularPrice;
    }

    /**
     * Highest and lowest daily CLOSE over the trailing 52 weeks (365 days) in the symbol's native
     * trade currency, each with the date it occurred (formatted "d M Y"). Built from the cached
     * Yahoo daily-close history so the chart modal's range bar can show a closing-based primary
     * range. Null when there are fewer than MIN_CLOSING_HISTORY_DAYS closes in the window.
     *
     * Parallel to DrawdownService::_computeClosingExtremes, which computes the same figure off the
     * already-loaded EUR price series for the watchlist table (so the cron does not re-fetch the
     * cache per symbol). This native-direct version backs the ad-hoc chart fetch. Both apply the
     * same minimum-history guard so the two surfaces agree on whether a range exists; keep them
     * consistent if either changes.
     *
     * @param float|null $currentPrice Live price to consider as a candidate extreme (bumps the
     *                                  closing high/low when it exceeds the historical range).
     * @return array{high:float,high_date:string,low:float,low_date:string}|null
     */
    public function closingExtremesNative(string $symbol, ?float $currentPrice = null): ?array
    {
        $fromDate = (new \DateTime('-365 days'))->format('Y-m-d');
        $toDate   = (new \DateTime())->format('Y-m-d');

        $fetched = (new HistoricalPriceCache())->fetch($symbol, $fromDate, $toDate);
        $prices  = $fetched['prices'] ?? [];
        if (empty($prices)) {
            return null;
        }

        $high = -INF;
        $low  = INF;
        $highDate = null;
        $lowDate  = null;
        $count    = 0;
        foreach ($prices as $date => $price) {
            if ($date < $fromDate) {
                continue;
            }
            $count++;
            if ($price > $high) {
                $high     = $price;
                $highDate = $date;
            }
            if ($price < $low) {
                $low     = $price;
                $lowDate = $date;
            }
        }

        if ($count < self::MIN_CLOSING_HISTORY_DAYS
            || $high <= 0.0 || $low <= 0.0 || $highDate === null || $lowDate === null) {
            return null;
        }

        if ($currentPrice !== null && $currentPrice > $high) {
            $high     = $currentPrice;
            $highDate = (new \DateTime())->format('Y-m-d');
        }
        if ($currentPrice !== null && $currentPrice < $low) {
            $low     = $currentPrice;
            $lowDate = (new \DateTime())->format('Y-m-d');
        }

        return [
            'high'      => round($high, 4),
            'high_date' => \Carbon\Carbon::parse($highDate)->format('d M Y'),
            'low'       => round($low, 4),
            'low_date'  => \Carbon\Carbon::parse($lowDate)->format('d M Y'),
        ];
    }

    /**
     * @param $exchangeRateData array(EURUSD => array(account_currency  => 'EUR',
     *                                                trade_currency    => 'USD'))
     *
     * @return $currencyPairs array(array('EUR', 'USD'))
     */
    public static function exchangeRateDataToCurrencyPairs(array $exchangeRateData)
        : array
    {
        if (empty($exchangeRateData)) {
            return [];
        }

        $currenciesReverseMapping = config('general.currencies_reverse_mapping');

        $currencyPairs = [];
        foreach ($exchangeRateData as $exchangeRateIndex => $exchangeRateDataItem) {
            if ($exchangeRateDataItem['account_currency'] ==
                $exchangeRateDataItem['trade_currency']
            ) {
                $exchangeRateData[$exchangeRateIndex]['exchange_rate'] = 1;
                continue;
            }
            $currencyPair = [
                $exchangeRateDataItem['account_currency'],
                $exchangeRateDataItem['trade_currency']
            ];
            if (!empty($currenciesReverseMapping[$currencyPair[0]])) {
                $currencyPair[0] = $currenciesReverseMapping[$currencyPair[0]];
            }
            if (!empty($currenciesReverseMapping[$currencyPair[1]])) {
                $currencyPair[1] = $currenciesReverseMapping[$currencyPair[1]];
            }
            $currencyPairs[] = $currencyPair;
        }
        // LOG::debug('exchangeRateData: ' . print_r($exchangeRateData, true));
        // LOG::debug('currencyPairs: ' . print_r($currencyPairs, true));

        return $currencyPairs;
    }

    /**
     * @param $currencyPairs array(array('EUR', 'USD'))
     * @param $date \DateTimeInterface
     *
     * @return $results array(HistoricalData)
     */
    public function getLastAvailableExchangeRates(array $currencyPairs,
        \DateTimeInterface $date, bool $persistStats = true): ?array
    {
        if (empty($currencyPairs)) {
            return [];
        }

        //NOTE there is no historical data when market is closed
        //      so we look for the day before or the day before that
        $maxDaysBefore = 7;
        $currentDaysBefore = 1;
        $financeAPI = new FinanceAPI();

        do {
            $currentDate = clone $date;
            $currentDate = $currentDate
                ->modify('-' . $currentDaysBefore . ' days');

            $results = $financeAPI->getHistoricalExchangeRates(
                $currencyPairs,
                $currentDate,
                $persistStats
            );

            if (!empty($results)) {
                LOG::info('We were not able to get the exchange rates for the '
                          . 'given date: '
                          . $date->format('Y-m-d')
                          . ', but were able to get the '
                          . 'exchange rates for date: '
                          . $currentDate->format('Y-m-d'));
                break;
            } else {
                LOG::info('Could NOT get the historical exchange rates for '
                          . 'date: '
                          . $currentDate->format('Y-m-d'));
            }
            $currentDaysBefore++;
        } while (empty($results)
                 && $currentDaysBefore <= $maxDaysBefore);

        if (empty($results)) {
            LOG::error('Could NOT get the historical exchange rates for'
                       . ' date: ' . $date->format('Y-m-d')
                       . '! This should never happen (FinanceUtils 163)! '
                       . 'We still failed after all these tries!');
            return null;
        }

        // LOG::debug('exchange rate results 165: ' . print_r($results, true));
        return $results;
    }


    public function getLastAvailableQuote(Quote $quote, \DateTimeInterface $date,
        bool $persistStats = true): ?HistoricalData
    {
        //NOTE there is no historical data when market is closed
        //      so we look for the day before or the day before that
        $maxDaysBefore = 7;
        $currentDaysBefore = 0;
        $financeAPI = new FinanceAPI();

        do {
            $currentDate = clone $date;
            $currentDate = $currentDate
                ->modify('-' . $currentDaysBefore . ' days');

            $historicalQuoteData = $financeAPI->getHistoricalQuoteData(
                $quote,
                $currentDate,
                persistStats: $persistStats
            );

            if (!empty($historicalQuoteData)) {
                /*
                LOG::debug('HistoricalQuoteData for symbol: '
                           . $quote->getSymbol() . ', date: '
                           . $currentDate->format('Y-m-d')
                           . ' => price: '
                           . $historicalQuoteData->getClose()
                           . ', quote_timestamp: '
                           . $historicalQuoteData->getDate()
                                                 ->format('Y-m-d'));
                */
            } else {
                LOG::info('Could NOT get the historical quote for symbol: '
                          . $quote->getSymbol() . ', date: '
                          . $currentDate->format('Y-m-d'));
            }
            $currentDaysBefore++;
        } while (empty($historicalQuoteData)
                 && $currentDaysBefore <= $maxDaysBefore);

        if (empty($historicalQuoteData)
            || !($historicalQuoteData instanceof HistoricalData)
        ) {
            LOG::error('Could NOT get the historical quote for symbol: '
                       . $quote->getSymbol() . ', date: ' . $date->format('Y-m-d')
                       . '! This should never happen (FinanceUtils 217)! '
                       . 'We still failed after all these tries!');
            return null;
        }

        return $historicalQuoteData;
    }

    /**
     * @param $exchangeRateData array(EURUSD => array(account_currency  => 'EUR',
     *                                                trade_currency    => 'USD'))
     * @param $date \DateTimeInterface
     *
     * @return $exchangeRateData array(EURUSD => array(account_currency => 'EUR',
     *                                                 trade_currency   => 'USD',
     *                                                 exchange_rate    => 1.1))
     */
    public function getExchangeRates(
        array $exchangeRateData,
        \DateTimeInterface $date = null,
        bool $persistStats = true
    ): ?array
    {
        if (empty($exchangeRateData)) {
            return [];
        }

        $currencyPairs = self::exchangeRateDataToCurrencyPairs($exchangeRateData);
        $financeAPI = new FinanceAPI();

        $results = [];
        if (!empty($date) && date('Y-m-d') != $date->format('Y-m-d')) {
            $results = $financeAPI->getHistoricalExchangeRates(
                $currencyPairs, $date, $persistStats
            );
        } else {
            $results = $financeAPI->getExchangeRates(
                $currencyPairs,
                persistStats: $persistStats
            );
        }

        if (empty($date)) {
            $date = new \DateTime();
        }

        if (empty($results)) {
            $results = $this->getLastAvailableExchangeRates(
                $currencyPairs, $date, $persistStats
            );
        }

        $currenciesMapping = config('general.currencies_mapping');
        $i = 0;
        foreach ($results as $result) {
            //NOTE If date is provided, we look at historical data
            if ($result instanceof Quote) {
                $exchangeRate = $result->getRegularMarketPrice();
            } else if ($result instanceof HistoricalData) {
                $exchangeRate = $result->getClose();
            } else {
                LOG::error('Unexpected result in FinanceUtils->getExchangeRates()! '
                           . print_r($result, true));
                return null;
            }
            if ($currencyPairs[$i][1] == 'GBp') { // The exchange rate is for GBP
                $exchangeRate *= 100;
            }
            if (!empty($currenciesMapping[$currencyPairs[$i][1]])) {
                $currencyPairs[$i][1] = $currenciesMapping[$currencyPairs[$i][1]];
            }

            // EURUSD
            $exchangeRateIndex = $currencyPairs[$i][0] . $currencyPairs[$i][1];
            $exchangeRateData[$exchangeRateIndex]['exchange_rate'] = $exchangeRate;

            $i++;
        }

        // LOG::debug('exchangeRateData 232: ' . print_r($exchangeRateData, true));
        return $exchangeRateData;
    }


    /**
     * @param array $symbols
     * @param \DateTimeInterface|null $date
     *
     * @return array(symbol => (price, currency, name, quote_timestamp, day_change))
     *         or null if failure
     */
    public function getQuotes(
        array $symbols,
        ?\DateTimeInterface $date = null,
        bool $persistStats = true
    ): ?array
    {
        $quotesArray = [];
        if (empty($symbols)) {
            return $quotesArray;
        }

        $financeAPI = new FinanceAPI();
        $quotes = $financeAPI->getQuotes(
            $symbols,
            persistStats: $persistStats
        );
        if (empty($quotes)) {
            return null;
        }
        // LOG::debug('quotes 311: ' . print_r($quotes, true));

        foreach ($quotes as $quote) {
            $currency = $quote->getCurrency();
            $quoteTimestamp = $quote->getRegularMarketTime();
            if (empty($quoteTimestamp)) {
                LOG::info('No quote timestamp! quote: ' . print_r($quote, true));
                continue;
            }
            /*
            $offset = self::get_timezone_offset(
                $quoteTimestamp->getTimezone()->getName());
            $quoteTimestamp->add(
                \DateInterval::createFromDateString((string)$offset . 'seconds'));
            */
            self::fixTimezone($quote, $quoteTimestamp);

            $sym = $quote->getSymbol();
            $quotesArray[$sym] = [
                'price'                    => $quote->getRegularMarketPrice(),
                'currency'                 => $currency,
                'name'                     => $quote->getLongName(),
                'quote_timestamp'          => $quoteTimestamp,
                'day_change'               => $quote->getRegularMarketChange(),
                'day_change_percentage'    => $quote->getRegularMarketChangePercent(),

                'regular_market_price'              => $quote->getRegularMarketPrice(),
                'regular_market_timestamp'         => clone $quoteTimestamp,
                'regular_market_day_change'        => $quote->getRegularMarketChange(),
                'regular_market_day_change_pct'    => $quote->getRegularMarketChangePercent(),

                'fiftyTwoWeekHigh'              => $quote->getFiftyTwoWeekHigh(),
                'fiftyTwoWeekHighChangePercent' =>
                    $quote->getFiftyTwoWeekHighChangePercent(),
                'fiftyTwoWeekLow'               => $quote->getFiftyTwoWeekLow(),
                'fiftyTwoWeekLowChangePercent'  =>
                    $quote->getFiftyTwoWeekLowChangePercent(),

                'fiftyDayAverage'                    => $quote->getFiftyDayAverage(),
                'fiftyDayAverageChangePercent'       => $quote->getFiftyDayAverageChangePercent(),
                'twoHundredDayAverage'               => $quote->getTwoHundredDayAverage(),
                'twoHundredDayAverageChangePercent'  => $quote->getTwoHundredDayAverageChangePercent(),
                'marketUtils' => new MarketUtils($quote),
            ];

            if (!empty($quote->getPostMarketPrice())) {
                $postMarketTs = $quote->getPostMarketTime();
                if ($postMarketTs instanceof \DateTime) {
                    self::fixTimezone($quote, $postMarketTs);
                }
                $quotesArray[$sym]['price']                 = $quote->getPostMarketPrice();
                $quotesArray[$sym]['quote_timestamp']        = $postMarketTs;
                $quotesArray[$sym]['post_market_price']      = $quote->getPostMarketPrice();
                $quotesArray[$sym]['post_market_timestamp']  = $postMarketTs;
            }
            if (!empty($quote->getPreMarketPrice())) {
                $preMarketTs = $quote->getPreMarketTime();
                if ($preMarketTs instanceof \DateTime) {
                    self::fixTimezone($quote, $preMarketTs);
                }
                $quotesArray[$sym]['price']                = $quote->getPreMarketPrice();
                $quotesArray[$sym]['quote_timestamp']       = $preMarketTs;
                $quotesArray[$sym]['pre_market_price']      = $quote->getPreMarketPrice();
                $quotesArray[$sym]['pre_market_timestamp']  = $preMarketTs;
            }

            // Post-market: the regular session already completed today, and Yahoo's
            // postMarketChange measures only the after-hours move (post-market price vs
            // the regular close). Cumulate it with the regular-session move so the Day
            // Gain columns and Today's movers reflect the complete day, recomputing the
            // percentage against the previous close. The raw after-hours delta is kept in
            // post_market_change / post_market_change_pct for the breakdown tooltips.
            $postMarketChange = $quote->getPostMarketChange();
            if (!empty($postMarketChange)) {
                $previousClose = $quote->getRegularMarketPreviousClose();
                if (empty($previousClose)) {
                    $previousClose = (float) $quote->getRegularMarketPrice()
                        - (float) $quote->getRegularMarketChange();
                }

                $cumulative = self::_cumulativePostMarketDayChange(
                    (float) $quote->getRegularMarketChange(),
                    (float) $postMarketChange,
                    $previousClose !== null ? (float) $previousClose : null,
                    (float) $quote->getRegularMarketChangePercent()
                );

                $quotesArray[$sym]['day_change']             = $cumulative['change'];
                $quotesArray[$sym]['day_change_percentage']  = $cumulative['percentage'];
                $quotesArray[$sym]['post_market_change']     = (float) $postMarketChange;
                $quotesArray[$sym]['post_market_change_pct'] = (float) $quote->getPostMarketChangePercent();
                $quotesArray[$sym]['post_market_day_change'] = true;
                $quotesArray[$sym]['post_market_day_change_percentage'] = true;
            }

            // Pre-market: preMarketChange is already measured against the previous close,
            // so it is the full "today so far" move (regularMarketChange still holds the
            // previous session until the market reopens). No cumulation needed.
            $preMarketChange = $quote->getPreMarketChange();
            if (!empty($preMarketChange)) {
                $quotesArray[$sym]['day_change']           = (float) $preMarketChange;
                $quotesArray[$sym]['pre_market_day_change'] = true;
            }
            $preMarketChangePct = $quote->getPreMarketChangePercent();
            if (!empty($preMarketChangePct)) {
                $quotesArray[$sym]['day_change_percentage']          = (float) $preMarketChangePct;
                $quotesArray[$sym]['pre_market_day_change_percentage'] = true;
            }

            // Recompute the 52-week high/low change percentages against the effective price (which
            // includes pre/post-market) so the watchlist "% High" / "% Low" columns stay consistent
            // with the live price shown in the same row, instead of Yahoo's regular-close-based
            // figures. In regular hours the effective price is the regular price, so this matches
            // Yahoo's values; in pre/post-market it tracks the live price. Same fraction units.
            $effectivePrice = $quotesArray[$sym]['price'] ?? null;
            $week52High     = $quote->getFiftyTwoWeekHigh();
            $week52Low      = $quote->getFiftyTwoWeekLow();
            if ($effectivePrice !== null && !empty($week52High) && $week52High > 0.0) {
                $quotesArray[$sym]['fiftyTwoWeekHighChangePercent'] =
                    ((float) $effectivePrice - (float) $week52High) / (float) $week52High;
            }
            if ($effectivePrice !== null && !empty($week52Low) && $week52Low > 0.0) {
                $quotesArray[$sym]['fiftyTwoWeekLowChangePercent'] =
                    ((float) $effectivePrice - (float) $week52Low) / (float) $week52Low;
            }

            //NOTE If we provide a date, we overwrite the price and quote timestamp
            if (!empty($date) && date('Y-m-d') != $date->format('Y-m-d')) {
                $historicalQuoteData = $this->getLastAvailableQuote(
                    $quote, $date, $persistStats
                );

                if (!empty($historicalQuoteData)) {
                    $quotesArray[$quote->getSymbol()]['price'] =
                        $historicalQuoteData->getClose();
                    $quotesArray[$quote->getSymbol()]['quote_timestamp'] =
                        $historicalQuoteData->getDate();
                } else {
                    $quotesArray[$quote->getSymbol()]['price'] = null;
                    $quotesArray[$quote->getSymbol()]['quote_timestamp'] = null;
                }
            }
        }

        return $quotesArray;
    }

    /**
     * Cumulative post-market day change (complete-day view).
     *
     * Yahoo's postMarketChange measures only the after-hours move (post-market
     * price vs the regular close), so it is added to the regular-session move to
     * describe the full day. The two raw percentages use different bases
     * (previous close for the regular session, regular close for after hours) and
     * cannot simply be summed, so the percentage is recomputed against the
     * previous close. Falls back to the regular-session percentage when the
     * previous close is unavailable.
     *
     * @return array{change: float, percentage: float}
     */
    private static function _cumulativePostMarketDayChange(
        float $regularChange,
        float $postMarketChange,
        ?float $previousClose,
        float $regularChangePercent
    ): array
    {
        $cumulativeChange = $regularChange + $postMarketChange;

        $percentage = !empty($previousClose)
            ? ($cumulativeChange / $previousClose) * 100
            : $regularChangePercent;

        return [
            'change'     => $cumulativeChange,
            'percentage' => $percentage,
        ];
    }

    /**
     * @param string  $symbol
     * @param integer $account_id
     * @param string  $timestamp
     * @param integer $tradeId
     *
     * @return float|null $availableQuantity or null if failure
     */
    public function getAvailableQuantity(
        string $symbol,
        int $account_id,
        ?string $timestamp = null,
        ?int $tradeId = null
    ): ?float {
        if (empty($account_id) || !is_numeric($account_id)) {
            LOG::error('Invalid account_id: ' . $account_id);
            return null;
        }
        if (empty($timestamp) || !\DateTime::createFromFormat(
            trans('myfinance2::general.datetime-format'), $timestamp)
        ) {
            $timestamp = date(trans('myfinance2::general.datetime-format'));
        }

        $availableQuantity = 0;
        $tradesQuery = Trade::whereDate('timestamp', '<=', $timestamp)
            ->where('symbol', $symbol)
            ->where('account_id', $account_id)
            ->orderBy('timestamp', 'asc');
        if (!empty($tradeId)) {
            $tradesQuery->where('id', '!=', $tradeId);
        }

        $trades = $tradesQuery->get();
        $trades->each(function ($trade) use (&$availableQuantity) {
            switch ($trade->action) {
                case 'BUY':
                    $availableQuantity += $trade->quantity;
                    break;
                case 'SELL':
                    $availableQuantity -= $trade->quantity;
                    break;
                default:
                    LOG::error('Unexpected action ' . $trade->action);
                    return null;
            }
        });

        $availableQuantity = round($availableQuantity) == $availableQuantity ?
            $availableQuantity : round($availableQuantity, 8);

        return $availableQuantity;
    }

    /**
     * Returns the offset from the origin timezone to the remote timezone,
     *      in seconds.
     *
     * @param $remote_tz;
     * @param $origin_tz; If null the servers current timezone is used
     *          as the origin.
     *
     * @return int; Offset in seconds (positive when origin is ahead of remote;
     *                                 negative otherwise)
     *              e.g. for MSFT (America/New_York) to Europe/Amsterdam,
     *                  offset is 21600s (6h)
     */
    public static function get_timezone_offset(
        string $remote_tz,
        ?string $origin_tz = null
    ): int|false {
        if ($origin_tz === null) {
            if(!is_string($origin_tz = date_default_timezone_get())) {
                return false; // A UTC timestamp was returned -- bail out!
            }
        }
        $origin_dtz = new \DateTimeZone($origin_tz);
        $remote_dtz = new \DateTimeZone($remote_tz);
        $origin_dt = new \DateTime("now", $origin_dtz);
        $remote_dt = new \DateTime("now", $remote_dtz);
        $offset = $origin_dtz->getOffset($origin_dt)
            - $remote_dtz->getOffset($remote_dt);
        return $offset;
    }

    /**
     * Build a map of symbol => formatted quote timestamp string for the given symbols.
     *
     * @param array $symbols
     *
     * @return array symbol => string
     */
    public function buildQuoteTimestamps(array $symbols): array
    {
        if (empty($symbols)) {
            return [];
        }

        $quotes = $this->getQuotes($symbols, null, false);

        if (!is_array($quotes)) {
            return [];
        }

        $timestamps = [];
        foreach ($symbols as $symbol) {
            $ts = $quotes[$symbol]['quote_timestamp'] ?? null;
            if ($ts instanceof \DateTime) {
                $timestamps[$symbol] = $ts->format(
                    trans('myfinance2::general.datetime-format')
                );
            }
        }

        return $timestamps;
    }

    /**
     * Fetch structured quote tooltip data for the given symbols (single API call).
     * Returns a map of symbol => array with price, timestamps, and pre/post market info.
     * Used to build rich price tooltips in views.
     *
     * @param string[] $symbols
     *
     * @return array<string, array>
     */
    public function buildQuoteData(array $symbols): array
    {
        if (empty($symbols)) {
            return [];
        }

        $quotes = $this->getQuotes($symbols, null, false);

        if (!is_array($quotes)) {
            return [];
        }

        $fmt    = trans('myfinance2::general.datetime-format');
        $result = [];
        foreach ($symbols as $symbol) {
            $q = $quotes[$symbol] ?? null;
            if (empty($q)) {
                continue;
            }

            $entry = [
                'price'                         => $q['price'] ?? null,
                'day_change'                    => $q['day_change'] ?? null,
                'day_change_pct'                => $q['day_change_percentage'] ?? null,
                'quote_timestamp'               => null,
                'regular_market_price'          => $q['regular_market_price'] ?? null,
                'regular_market_day_change'     => $q['regular_market_day_change'] ?? null,
                'regular_market_day_change_pct' => $q['regular_market_day_change_pct'] ?? null,
                'regular_market_timestamp'      => null,
                'pre_market_price'              => $q['pre_market_price'] ?? null,
                'pre_market_timestamp'          => null,
                'post_market_price'             => $q['post_market_price'] ?? null,
                'post_market_change'            => $q['post_market_change'] ?? null,
                'post_market_change_pct'        => $q['post_market_change_pct'] ?? null,
                'post_market_timestamp'         => null,
                'market_open'                   => isset($q['marketUtils'])
                    && $q['marketUtils']->isOpen(),
            ];

            if (($q['quote_timestamp'] ?? null) instanceof \DateTime) {
                $entry['quote_timestamp'] = $q['quote_timestamp']->format($fmt);
            }
            if (($q['regular_market_timestamp'] ?? null) instanceof \DateTime) {
                $entry['regular_market_timestamp'] = $q['regular_market_timestamp']->format($fmt);
            }
            if (($q['pre_market_timestamp'] ?? null) instanceof \DateTime) {
                $entry['pre_market_timestamp'] = $q['pre_market_timestamp']->format($fmt);
            }
            if (($q['post_market_timestamp'] ?? null) instanceof \DateTime) {
                $entry['post_market_timestamp'] = $q['post_market_timestamp']->format($fmt);
            }

            $result[$symbol] = $entry;
        }

        return $result;
    }

    /**
     * Fetch current market prices for the given symbols.
     * Returns a map of symbol => float price (symbols with no quote are omitted).
     *
     * @param string[] $symbols
     *
     * @return array<string, float>
     */
    public function getPricesBySymbol(array $symbols): array
    {
        if (empty($symbols)) {
            return [];
        }

        $quotes = $this->getQuotes($symbols, null, false);

        if (!is_array($quotes)) {
            return [];
        }

        $prices = [];
        foreach ($symbols as $symbol) {
            if (isset($quotes[$symbol]['price'])) {
                $prices[$symbol] = (float) $quotes[$symbol]['price'];
            }
        }

        return $prices;
    }

    public static function fixTimezone(Quote $quote, \DateTime $timestamp): void
    {
        $timestamp->setTimezone(new \DateTimeZone('Europe/Amsterdam'));

        /* NOTE We don't need the condition! We always convert the timezone
        if (!str_contains($quote->getExchangeTimezoneName(), 'Europe/Amsterdam')) {
            // LOG::debug("Fixing timezone for symbol " . $quote->getSymbol() . ", quote: "
            //     . print_r($quote, true));
            $timestamp->setTimezone(new \DateTimeZone('Europe/Amsterdam'));
            // LOG::debug("timestamp: " . $timestamp->format("Y-m-d H:i:s"));
        }
        */
    }
}


<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Scheb\YahooFinanceApi\Results\Quote;

/**
 * The overnight session of US stocks (20:00 to 04:00 New York time). It opens the next trading day,
 * so it is shown the way pre-market is: its price is the latest one and its day change is measured
 * against the last regular close (as Yahoo does), so pre-market continues it without a jump. A
 * quote row in the overnight session therefore carries the pre-market fields, plus the
 * overnight_market flag that only changes the labels (MarketUtils::getQuoteSession()).
 */
class OvernightSession
{
    private const TIMEZONE   = 'America/New_York';
    private const OPEN_HOUR  = 20;
    private const CLOSE_HOUR = 4;

    /**
     * Whether the overnight session is running: 20:00 to 04:00 New York time, from Sunday evening
     * to Friday morning (no session from Friday 20:00 to Sunday 20:00). Market holidays are not
     * modelled.
     */
    public static function isOpen(?\DateTimeInterface $now = null): bool
    {
        $newYork = \DateTimeImmutable::createFromInterface($now ?? new \DateTimeImmutable())
            ->setTimezone(new \DateTimeZone(self::TIMEZONE));
        $hour = (int) $newYork->format('G');
        $day  = (int) $newYork->format('w'); // 0 = Sunday ... 6 = Saturday

        if ($hour >= self::OPEN_HOUR) {
            return $day <= 4; // Sunday to Thursday evening
        }

        return $hour < self::CLOSE_HOUR && $day >= 1 && $day <= 5; // Monday to Friday morning
    }

    /**
     * The overnight price and time when they are newer than every other session's (regular, pre
     * and post-market); null outside the overnight session or for a quote without overnight data.
     * The time is not timezone-fixed.
     *
     * @return array{0: float, 1: \DateTime}|null [price, time]
     */
    public static function latest(Quote $quote): ?array
    {
        if (!($quote instanceof LiveQuote)) {
            return null;
        }
        $price = $quote->getOvernightMarketPrice();
        $time  = $quote->getOvernightMarketTime();
        if (empty($price) || !($time instanceof \DateTimeInterface)) {
            return null;
        }

        $others = [
            $quote->getRegularMarketTime(),
            !empty($quote->getPreMarketPrice()) ? $quote->getPreMarketTime() : null,
            !empty($quote->getPostMarketPrice()) ? $quote->getPostMarketTime() : null,
        ];
        foreach ($others as $other) {
            if ($other instanceof \DateTimeInterface && $other->getTimestamp() >= $time->getTimestamp()) {
                return null;
            }
        }

        return [$price, $time];
    }

    /**
     * Rewrites a FinanceUtils::getQuotes() row for the overnight session: the overnight price
     * becomes the row's price and its pre-market price, the day change is measured against the
     * last regular close, and the after-hours figures (the previous session's) are dropped.
     *
     * @param array<string, mixed> $row the row built from the quote's regular / pre / post fields
     *
     * @return array<string, mixed> the row unchanged when the overnight price is not the latest
     */
    public static function apply(array $row, Quote $quote): array
    {
        $latest = self::latest($quote);
        if ($latest === null) {
            return $row;
        }
        [$price, $time] = $latest;
        FinanceUtils::fixTimezone($quote, $time);

        foreach (array_keys($row) as $key) {
            if (str_starts_with((string) $key, 'post_market_')) {
                unset($row[$key]);
            }
        }

        $row['price']                = $price;
        $row['quote_timestamp']      = $time;
        $row['pre_market_price']     = $price;
        $row['pre_market_timestamp'] = $time;
        $row['overnight_market']     = true;

        $close = (float) $quote->getRegularMarketPrice();
        if ($close > 0.0) {
            $row['day_change']                       = $price - $close;
            $row['day_change_percentage']            = ($price - $close) / $close * 100;
            $row['pre_market_day_change']            = true;
            $row['pre_market_day_change_percentage'] = true;
        }

        return $row;
    }
}

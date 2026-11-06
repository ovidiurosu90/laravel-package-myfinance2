<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Scheb\YahooFinanceApi\Results\Quote;
use Scheb\YahooFinanceApi\ValueMapperInterface;

/**
 * A Yahoo quote that also carries the fields scheb/yahoo-finance-api does not map: the overnight
 * session (20:00 to 04:00 New York time, traded on Blue Ocean ATS). Without them a US quote keeps
 * the last after-hours price (19:59) until pre-market opens at 04:00. Built by LiveQuoteDecoder;
 * it is still a Quote, so the cache (serialized) and every Quote consumer keep working.
 */
class LiveQuote extends Quote
{
    /** Yahoo quote fields the library drops, with the value type they are mapped to. */
    public const EXTRA_FIELDS = [
        'overnightMarketPrice'         => ValueMapperInterface::TYPE_FLOAT,
        'overnightMarketTime'          => ValueMapperInterface::TYPE_DATE,
        'overnightMarketChange'        => ValueMapperInterface::TYPE_FLOAT,
        'overnightMarketChangePercent' => ValueMapperInterface::TYPE_FLOAT,
    ];

    /** @var array<string, mixed> the mapped EXTRA_FIELDS present in the response */
    private array $_extra;

    /**
     * @param array<string, mixed> $values the library's mapped quote values
     * @param array<string, mixed> $extra  mapped EXTRA_FIELDS values
     */
    public function __construct(array $values, array $extra = [])
    {
        parent::__construct($values);
        $this->_extra = array_intersect_key($extra, self::EXTRA_FIELDS);
    }

    /**
     * This quote with the overnight fields of the same symbol's flagged quote (OvernightQuotes): every other field
     * stays this one's, so the after-hours prices the flagged response leaves out are kept.
     */
    public function withOvernightOf(self $flagged): self
    {
        return new self(parent::jsonSerialize(), $flagged->_extra + $this->_extra);
    }

    public function getOvernightMarketPrice(): ?float
    {
        return $this->_extra['overnightMarketPrice'] ?? null;
    }

    public function getOvernightMarketTime(): ?\DateTime
    {
        return $this->_extra['overnightMarketTime'] ?? null;
    }

    public function getOvernightMarketChange(): ?float
    {
        return $this->_extra['overnightMarketChange'] ?? null;
    }

    public function getOvernightMarketChangePercent(): ?float
    {
        return $this->_extra['overnightMarketChangePercent'] ?? null;
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), $this->_extra);
    }
}

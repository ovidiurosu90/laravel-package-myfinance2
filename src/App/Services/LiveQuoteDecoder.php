<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;
use Scheb\YahooFinanceApi\Exception\InvalidValueException;
use Scheb\YahooFinanceApi\ResultDecoder;
use Scheb\YahooFinanceApi\ValueMapperInterface;

/**
 * Decodes Yahoo quotes the library's way, then wraps each one in a LiveQuote holding the fields the
 * library drops (LiveQuote::EXTRA_FIELDS). An extra field with an unexpected value is skipped (and
 * logged) instead of failing the whole quote, since the library's own fields are what matter most.
 */
class LiveQuoteDecoder extends ResultDecoder
{
    private ValueMapperInterface $_valueMapper;

    public function __construct(ValueMapperInterface $valueMapper)
    {
        parent::__construct($valueMapper);
        $this->_valueMapper = $valueMapper;
    }

    public function transformQuotes(string $responseBody): array
    {
        $quotes  = parent::transformQuotes($responseBody);
        $results = json_decode($responseBody, true)['quoteResponse']['result'];

        // The library maps the results in order, keeping their keys, so both arrays line up.
        $liveQuotes = [];
        foreach ($quotes as $index => $quote) {
            $liveQuotes[] = new LiveQuote(
                $quote->jsonSerialize(),
                $this->_extraFields((array) ($results[$index] ?? []))
            );
        }

        return $liveQuotes;
    }

    /**
     * @param array<string, mixed> $json one raw quote result
     *
     * @return array<string, mixed> the mapped LiveQuote::EXTRA_FIELDS present in it
     */
    private function _extraFields(array $json): array
    {
        $extra = [];
        foreach (LiveQuote::EXTRA_FIELDS as $field => $type) {
            if (!isset($json[$field])) {
                continue;
            }
            try {
                $extra[$field] = $this->_valueMapper->mapValue($json[$field], $type);
            } catch (InvalidValueException $e) {
                Log::info('Skipping Yahoo quote field ' . $field . ' of ' . ($json['symbol'] ?? '?')
                    . ': ' . $e->getMessage());
            }
        }

        return $extra;
    }
}

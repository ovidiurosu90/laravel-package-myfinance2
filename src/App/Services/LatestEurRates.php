<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Models\StatToday;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

/**
 * Today's EUR per unit of a trade currency, from the stored FX quotes: the latest of the day (stats_today), else the
 * last stored close (stats_historical). Pence (GBp / GBX) are a hundredth of GBP. Cached per instance and day; null
 * for a currency without an FX pair here or without a stored rate. Used by the price alerts (EUR figures) and the
 * trade list (its Amount column sorts in EUR).
 */
final class LatestEurRates
{
    /** currency => [FX symbol (EUR per it inverted), scale] */
    private const PAIRS = [
        'USD' => ['EURUSD=X', 1],
        'GBP' => ['EURGBP=X', 1],
        'GBp' => ['EURGBP=X', 100],
        'GBX' => ['EURGBP=X', 100],
    ];

    private array $_cache = [];

    public function forCurrency(string $currency): ?float
    {
        if ($currency === 'EUR') {
            return 1.0;
        }
        $key = $currency . ':' . date('Y-m-d');
        if (array_key_exists($key, $this->_cache)) {
            return $this->_cache[$key];
        }
        $pair = self::PAIRS[$currency] ?? null;

        return $this->_cache[$key] = $pair === null ? null : $this->_fetch($pair[0], $pair[1]);
    }

    private function _fetch(string $symbol, int $scale): ?float
    {
        $row = StatToday::withoutGlobalScope(AssignedToUserScope::class)
            ->where('symbol', $symbol)
            ->orderBy('timestamp', 'DESC')
            ->first();

        if (empty($row) || (float) $row->unit_price <= 0) {
            $row = StatHistorical::withoutGlobalScope(AssignedToUserScope::class)
                ->where('symbol', $symbol)
                ->where('date', '<=', date('Y-m-d'))
                ->orderBy('date', 'DESC')
                ->first();
        }

        if (empty($row) || (float) $row->unit_price <= 0) {
            return null;
        }

        return 1.0 / ((float) $row->unit_price * $scale);
    }
}

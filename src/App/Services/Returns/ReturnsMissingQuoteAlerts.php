<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

/**
 * Returns alert for held positions that could not be priced.
 *
 * ReturnsValuation leaves a position out of the Jan 1 / Dec 31 value when neither a price override nor
 * the finance API has a price for it, so the account value silently drops by that position. This turns
 * the recorded gaps (jan1MissingQuotes / dec31MissingQuotes) into a visible alert. The usual cause is a
 * delisted symbol whose history the API no longer serves; the fix is a price_overrides entry (plus a
 * delisted_symbols entry so the API is not asked again).
 */
class ReturnsMissingQuoteAlerts
{
    /**
     * @param array $returnsData The data from Returns::handle()
     * @param int   $year        The year being viewed
     * @return array Zero, one or two alerts (Jan 1 and/or Dec 31)
     */
    public function check(array $returnsData, int $year): array
    {
        $alerts = [];

        foreach (['jan1', 'dec31'] as $dateType) {
            $positions = $this->_collect($returnsData, $dateType . 'MissingQuotes');
            if (empty($positions)) {
                continue;
            }

            $alerts[] = [
                'type' => 'missing_quote_' . $dateType,
                'message' => $this->_message($dateType, $year),
                'positions' => $positions,
            ];
        }

        return $alerts;
    }

    /**
     * Gather the missing quotes of every real account, sorted by symbol then account
     */
    private function _collect(array $returnsData, string $key): array
    {
        $delisted = config('trades.delisted_symbols', []);
        $positions = [];

        foreach ($returnsData as $accountId => $data) {
            // Skip metadata entries and virtual accounts
            if (!is_numeric($accountId) || !is_array($data)) {
                continue;
            }

            foreach ($data[$key] ?? [] as $missing) {
                $positions[] = [
                    'symbol' => $missing['symbol'],
                    'quantity' => $missing['quantity'],
                    'date' => $missing['date'],
                    'account_id' => (int) $accountId,
                    'account_name' => $data['account']->name ?? "Account #$accountId",
                    'user_id' => $data['account']->user_id ?? null,
                    'delisted' => in_array($missing['symbol'], $delisted, true),
                ];
            }
        }

        usort($positions, fn($a, $b) => [$a['symbol'], $a['account_name']] <=> [$b['symbol'], $b['account_name']]);

        return $positions;
    }

    private function _message(string $dateType, int $year): string
    {
        if ($dateType === 'jan1') {
            return "Positions without a price on Jan 1, $year are valued at 0 in the start value "
                . "(config date: " . ($year - 1) . "-12-31)";
        }

        return "Positions without a price on Dec 31, $year are valued at 0 in the end value "
            . "(config date: $year-12-31)";
    }
}

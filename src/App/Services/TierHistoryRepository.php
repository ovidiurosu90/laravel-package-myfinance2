<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\SymbolTierHistory;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

/**
 * Reads the stored daily tier history. Read with the user scope dropped and an explicit user_id so
 * the cron (no authenticated user) and the dashboard agree. A missing table (migration not run yet)
 * reads as no history instead of breaking the page.
 */
final class TierHistoryRepository
{
    /**
     * The lookback window of a day D: rows dated [D - $days, D - 1], strictly before D, so today's
     * reading is compared against the window and never part of it.
     *
     * @return array<string, array<string, array{v: ?float, m: ?float, w: ?float, h: ?float, s: ?string,
     *         b: string, o: bool}>>
     *         symbol => date => [v: deciding value, m: market 1Y, w: own return, h: holding period return,
     *         s: holding period start, b: basis, o: owned], dates ascending
     * @param ?array $symbols Only these symbols; null for all of the user's.
     */
    public function loadWindow(int $userId, Carbon $day, int $days, ?array $symbols = null): array
    {
        $from = $day->copy()->subDays($days)->format('Y-m-d');
        $to   = $day->copy()->subDay()->format('Y-m-d');

        return $this->_load($userId, $symbols, $from, $to);
    }

    /**
     * Every stored row of one symbol, oldest first (the tier history chart).
     *
     * @return array<string, array{v: ?float, m: ?float, w: ?float, h: ?float, s: ?string, b: string, o: bool}>
     *         date => row
     */
    public function loadSymbol(int $userId, string $symbol): array
    {
        return $this->_load($userId, [$symbol], null, null)[$symbol] ?? [];
    }

    /**
     * Every stored row of every one of the user's symbols, oldest first (the signal backtest).
     *
     * @return array<string, array<string, array{v: ?float, m: ?float, w: ?float, h: ?float, s: ?string,
     *         b: string, o: bool}>>
     */
    public function loadAll(int $userId): array
    {
        return $this->_load($userId, null, null, null);
    }

    private function _load(int $userId, ?array $symbols, ?string $from, ?string $to): array
    {
        try {
            $rows = SymbolTierHistory::withoutGlobalScope(AssignedToUserScope::class)
                ->where('user_id', $userId)
                ->when($symbols !== null, fn ($q) => $q->whereIn('symbol', $symbols))
                ->when($from !== null, fn ($q) => $q->where('date', '>=', $from))
                ->when($to !== null, fn ($q) => $q->where('date', '<=', $to))
                ->orderBy('date')
                ->toBase()
                ->get([
                    'symbol', 'date', 'basis', 'basis_value', 'market_value', 'own_value', 'holding_value',
                    'holding_since', 'is_owned',
                ]);
        } catch (QueryException $e) {
            Log::warning('TierHistoryRepository: tier history unavailable: ' . $e->getMessage());
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $result[$row->symbol][substr((string) $row->date, 0, 10)] = [
                'v' => $this->_float($row->basis_value),
                'm' => $this->_float($row->market_value),
                'w' => $this->_float($row->own_value),
                'h' => $this->_float($row->holding_value),
                's' => $row->holding_since !== null ? substr((string) $row->holding_since, 0, 10) : null,
                'b' => (string) $row->basis,
                'o' => (bool) $row->is_owned,
            ];
        }

        return $result;
    }

    private function _float(mixed $value): ?float
    {
        return $value !== null ? (float) $value : null;
    }
}

<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

/**
 * A symbol's position figures as the alert emails show them: the unrealized gain if sold now (market value minus
 * cost2, fees included, in EUR across every account holding it) and today's move (the day's P&L in EUR and the
 * symbol's daily price change %). One source for the watchlist dashboard (so the peak-proximity alerts) and the
 * price alert email, so a holding reads the same figures in every email and on /positions. A symbol not held still
 * has today's move %, from its quote.
 */
class SymbolPositionReturns
{
    /** User id => symbol => figures, cached per instance (one Positions run per user per evaluation pass). */
    private array $_byUser = [];

    /**
     * The figures of one symbol from its open positions (one per account), or null when it has none.
     *
     * @param array $positions Positions::handle() open positions of the symbol
     * @param array $eurRates  trade/account currency => EUR multiplier (liveEurRates())
     *
     * @return array|null ['raw_pct', 'gain_eur', 'today_gain_eur', 'today_gain_pct', 'days', 'quantity',
     *                    'avg_unit_cost2'] (avg_unit_cost2: fees included, in the trade currency)
     */
    public static function fromOpenPositions(array $positions, array $eurRates): ?array
    {
        if (empty($positions)) {
            return null;
        }

        $mvalue    = 0.0;
        $cost      = 0.0;
        $todayEur  = 0.0;
        $todayPct  = null;
        $earliest  = null;
        $quantity  = 0.0;
        $tradeCost = 0.0;
        foreach ($positions as $position) {
            $currency   = $position['accountModel']->currency->iso_code ?? 'EUR';
            $rate       = (float) ($eurRates[$currency] ?? 1.0);
            $mvalue    += (float) ($position['market_value_in_account_currency'] ?? 0.0) * $rate;
            $cost      += (float) ($position['cost2_in_account_currency'] ?? 0.0) * $rate;
            $todayEur  += (float) ($position['day_change_in_account_currency'] ?? 0.0) * $rate;
            $quantity  += (float) ($position['quantity'] ?? 0.0);
            $tradeCost += (float) ($position['cost2_in_trade_currency'] ?? 0.0);
            // Same daily price move across every account holding the symbol; keep the first seen.
            if ($todayPct === null && ($position['day_change_percentage'] ?? null) !== null) {
                $todayPct = (float) $position['day_change_percentage'];
            }
            foreach ($position['trades'] ?? [] as $trade) {
                if ($earliest === null || $trade->timestamp < $earliest) {
                    $earliest = $trade->timestamp;
                }
            }
        }

        return [
            'raw_pct'        => $cost > 0.0 ? ($mvalue - $cost) / $cost * 100.0 : null,
            'gain_eur'       => round($mvalue - $cost, 2),
            'today_gain_eur' => round($todayEur, 2),
            'today_gain_pct' => $todayPct,
            'days'           => $earliest !== null ? (int) $earliest->diffInDays(now()) : 0,
            'quantity'       => $quantity,
            'avg_unit_cost2' => $quantity > 0.0 ? $tradeCost / $quantity : null,
        ];
    }

    /**
     * Live EUR multipliers per currency from Positions::handle()'s exchange rate data (the rates /positions uses).
     *
     * @param array $exchangeRateData
     *
     * @return array currency => EUR multiplier
     */
    public static function liveEurRates(array $exchangeRateData): array
    {
        $rates = ['EUR' => 1.0];
        foreach ($exchangeRateData as $data) {
            if (($data['account_currency'] ?? null) === 'EUR'
                && !empty($data['exchange_rate'])
                && $data['exchange_rate'] > 0
            ) {
                $rates[$data['trade_currency']] = 1.0 / (float) $data['exchange_rate'];
            }
        }
        if (isset($rates['GBP'])) {
            $rates['GBp'] = $rates['GBP'] / 100.0;
            $rates['GBX'] = $rates['GBP'] / 100.0;
        }

        return $rates;
    }

    /**
     * Today's move % of a symbol from its quote (pre/post-market aware, the figure a held position carries), for a
     * symbol not held. Null when the quote has none.
     *
     * @param array|null $quote FinanceUtils::getQuotes() entry
     *
     * @return float|null
     */
    public static function quoteDayChangePct(?array $quote): ?float
    {
        $pct = $quote['day_change_percentage'] ?? null;

        return is_numeric($pct) ? (float) $pct : null;
    }

    /**
     * Every held symbol's figures for a user (symbol => fromOpenPositions()), computed as that user from
     * Positions::handle() so they match /positions and the watchlist. Runs once per user per instance; an empty
     * array when the positions cannot be loaded (the email then goes without them).
     *
     * @param int $userId
     *
     * @return array
     */
    public function forUser(int $userId): array
    {
        if (!array_key_exists($userId, $this->_byUser)) {
            $this->_byUser[$userId] = $this->_load($userId);
        }

        return $this->_byUser[$userId];
    }

    /**
     * Run Positions::handle() as the user (read-only: the user scope on, auth set), restoring the caller's scope
     * and auth state after, then group the open positions by symbol.
     *
     * @param int $userId
     *
     * @return array
     */
    private function _load(int $userId): array
    {
        $user = User::find($userId);
        if ($user === null) {
            return [];
        }

        $previousUser    = auth()->user();
        $scopeWasEnabled = AssignedToUserScope::isEnabled();
        try {
            auth()->setUser($user);
            AssignedToUserScope::enable();
            $positionsService = new Positions();
            $positionsService->setPersistStats(false);
            $positionsData = $positionsService->handle();
        } catch (\Throwable $e) {
            Log::warning("SymbolPositionReturns: positions for user #{$userId} failed: " . $e->getMessage());
            return [];
        } finally {
            if (!$scopeWasEnabled) {
                AssignedToUserScope::disable();
            }
            if ($previousUser !== null) {
                auth()->setUser($previousUser);
            } else {
                auth()->forgetGuards();
            }
        }

        $bySymbol = [];
        foreach ($positionsData['groupedItems'] ?? [] as $openPositions) {
            foreach ($openPositions as $openPosition) {
                $bySymbol[$openPosition['symbol']][] = $openPosition;
            }
        }

        $eurRates = self::liveEurRates($positionsData['exchangeRateData'] ?? []);
        $returns  = [];
        foreach ($bySymbol as $symbol => $positions) {
            $returns[$symbol] = self::fromOpenPositions($positions, $eurRates);
        }

        return $returns;
    }
}

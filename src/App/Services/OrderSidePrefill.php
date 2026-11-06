<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use ovidiuro\myfinance2\App\Models\Account;
use ovidiuro\myfinance2\App\Models\Trade;

/**
 * What the order form fills once the user picks a side, for both sides at once so switching needs no new request:
 * the account the side suggests, the limit (1 % from the price), the quantity and the weak-signal flag
 * (OrderSuggestion::forSide()).
 *
 * The account follows the side:
 *   SELL: the account holding the most of the symbol (null when nothing is held); the others are listed so the form
 *         can say where else it is held.
 *   BUY:  the trade account the symbol was last bought in, else the trade account in the symbol's currency with the
 *         most cash.
 * The figures are computed for the account the user selected, else for the side's own account.
 */
class OrderSidePrefill
{
    /** @var array<int, string|null> account id => currency ISO code */
    private array $_currencies = [];

    public function __construct(private readonly OrderSuggestion $_suggestion = new OrderSuggestion())
    {
    }

    /**
     * @param string   $symbol
     * @param array    $financeData       FinanceUtils::getFinanceDataBySymbol(), currency normalized
     * @param float    $eurRate           EUR → trade currency (1.0 for EUR)
     * @param int|null $selectedAccountId the account chosen in the form, if any
     *
     * @return array{held: bool, holdings: array<int, array{account_id: int, name: string, quantity: float}>,
     *               BUY: array, SELL: array}
     */
    public function build(string $symbol, array $financeData, float $eurRate, ?int $selectedAccountId): array
    {
        $holdings = $this->holdings($symbol);
        $accounts = [
            'BUY'  => $this->buyAccount($symbol, (string) $financeData['currency']),
            'SELL' => $holdings[0]['account_id'] ?? null,
        ];
        $heldIn = array_column($holdings, 'quantity', 'account_id');

        $sides = [];
        foreach ($accounts as $side => $suggestedId) {
            $accountId    = $selectedAccountId ?? $suggestedId;
            $sides[$side] = ['account_id' => $suggestedId] + $this->_suggestion->forSide(
                $side,
                $financeData,
                $eurRate,
                $this->_currency($accountId),
                $accountId !== null ? (float) ($heldIn[$accountId] ?? 0.0) : 0.0
            );
        }

        return ['held' => !empty($holdings), 'holdings' => $holdings] + $sides;
    }

    /**
     * The accounts holding the symbol (bought minus sold, above zero), the largest holding first.
     *
     * @return array<int, array{account_id: int, name: string, quantity: float}>
     */
    public function holdings(string $symbol): array
    {
        $byAccount = [];
        $trades    = Trade::where('symbol', $symbol)->whereIn('action', ['BUY', 'SELL'])
            ->get(['account_id', 'action', 'quantity']);
        foreach ($trades as $trade) {
            $sign = $trade->action === 'BUY' ? 1 : -1;
            $byAccount[(int) $trade->account_id] = ($byAccount[(int) $trade->account_id] ?? 0.0)
                + $sign * (float) $trade->quantity;
        }
        $byAccount = array_filter($byAccount, fn (float $quantity) => round($quantity, 8) > 0);
        if (empty($byAccount)) {
            return [];
        }

        $names    = Account::whereIn('id', array_keys($byAccount))->pluck('name', 'id');
        $holdings = [];
        foreach ($byAccount as $accountId => $quantity) {
            $holdings[] = [
                'account_id' => $accountId,
                'name'       => (string) ($names[$accountId] ?? '#' . $accountId),
                'quantity'   => round($quantity, 8),
            ];
        }
        usort($holdings, fn ($a, $b) => [$b['quantity'], $a['account_id']] <=> [$a['quantity'], $b['account_id']]);

        return $holdings;
    }

    /**
     * The trade account the symbol was last bought in, else the trade account in its currency with the most cash.
     */
    public function buyAccount(string $symbol, string $currencyIsoCode): ?int
    {
        $lastBuy = Trade::where('symbol', $symbol)->where('action', 'BUY')
            ->whereHas('accountModel', fn ($query) => $query->where('is_trade_account', 1))
            ->orderByDesc('timestamp')
            ->value('account_id');

        return $lastBuy !== null ? (int) $lastBuy : self::accountForCurrency($currencyIsoCode);
    }

    /**
     * The trade account with the highest cash balance in a currency.
     */
    public static function accountForCurrency(string $currencyIsoCode): ?int
    {
        $accounts = Account::with('currency')
            ->where('is_trade_account', 1)
            ->whereHas('currency', fn ($query) => $query->where('iso_code', $currencyIsoCode))
            ->get();

        $bestAccountId = null;
        $bestBalance   = null;
        foreach ($accounts as $account) {
            $balance = (new CashBalancesUtils($account->id, null, $account))->getAmount();
            if ($balance !== null && ($bestBalance === null || $balance > $bestBalance)) {
                $bestBalance   = $balance;
                $bestAccountId = $account->id;
            }
        }

        return $bestAccountId;
    }

    private function _currency(?int $accountId): ?string
    {
        if ($accountId === null) {
            return null;
        }
        if (!array_key_exists($accountId, $this->_currencies)) {
            $this->_currencies[$accountId] = Account::with('currency')->find($accountId)?->currency?->iso_code;
        }

        return $this->_currencies[$accountId];
    }
}

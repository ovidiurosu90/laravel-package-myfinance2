<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Record of the exact prices the minutely cron used to build the stored account and User
 * Overview series: per position the quantity, price and exchange rate, per account the cash,
 * plus the EURUSD rate of the EUR/USD conversion and the time it was taken.
 *
 * The /positions reconciliation reprices the live position rows with these prices, so the stored
 * series can be compared to the rows exactly, no matter how far prices moved since the snapshot.
 *
 * The cron marks the snapshot incomplete before it rewrites the chart files and completes it
 * after, so a page load landing mid-write skips the check instead of comparing a new chart with
 * an old snapshot.
 */
class PositionsSnapshot
{
    private const _FILE = 'snapshot.json';
    private const _QTY_EPSILON = 0.000001;

    public static function path(int $userId): string
    {
        return 'charts' . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR
            . $userId . DIRECTORY_SEPARATOR . self::_FILE;
    }

    /**
     * Flag the snapshot as being rewritten, before the cron touches the chart files.
     */
    public static function markWriting(int $userId): void
    {
        self::_put($userId, ['complete' => false, 'taken_at' => time()]);
    }

    public static function write(int $userId, array $snapshot): void
    {
        self::_put($userId, $snapshot);
    }

    public static function read(int $userId): ?array
    {
        $path = self::path($userId);
        $disk = Storage::disk('local');
        if (!$disk->exists($path)) {
            return null;
        }

        $snapshot = json_decode((string) $disk->get($path), true);

        return is_array($snapshot) ? $snapshot : null;
    }

    /**
     * Build one snapshot per user from the cron's Positions::handle() result. Only positions the
     * cron could price (with a market value) are recorded, the same ones its account totals sum.
     *
     * @return array<int, array<string, mixed>> Snapshots keyed by user id.
     */
    public static function build(array $groupedItems, array $accountData, ?float $eurusd,
        int $takenAt): array
    {
        $snapshots = [];

        foreach ($groupedItems as $accountId => $items) {
            $account = $accountData[$accountId] ?? null;
            if (empty($account['accountModel'])) {
                continue;
            }

            $userId = (int) $account['accountModel']->user_id;
            $snapshots[$userId] ??= [
                'complete' => true,
                'taken_at' => $takenAt,
                'eurusd' => $eurusd,
                'accounts' => [],
            ];
            $snapshots[$userId]['accounts'][$accountId] = [
                'currency' => $account['accountModel']->currency->iso_code,
                'cash' => self::cashOf($account),
                'positions' => self::_positionsOf($items),
            ];
        }

        return $snapshots;
    }

    /**
     * Cash the cron persists for an account (the last cash balance, 0 when none).
     */
    public static function cashOf(array $account): float
    {
        $cashBalance = !empty($account['cashBalanceUtils'])
            ? $account['cashBalanceUtils']->getLastCashBalance()
            : null;

        return empty($cashBalance) ? 0.0 : (float) $cashBalance->amount;
    }

    /**
     * Reprice an account's live rows at the snapshot's prices. Cost does not depend on price, so
     * it stays; market value is recomputed and change follows it. Returns null when the positions
     * changed since the snapshot (a trade in between), because the rows then describe a different
     * portfolio than the stored series and cannot be compared.
     *
     * @return array{mvalue: float, cost: float, change: float}|null
     */
    public static function repriceAccount(array $items, array $snapshotAccount): ?array
    {
        $snapPositions = $snapshotAccount['positions'] ?? [];
        $sums = ['mvalue' => 0.0, 'cost' => 0.0, 'change' => 0.0];
        $priced = 0;

        foreach ($items as $symbol => $item) {
            if (!isset($item['market_value_in_account_currency'])) {
                continue;
            }

            $snap = $snapPositions[$symbol] ?? null;
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($snap === null || abs($quantity - (float) $snap['quantity']) > self::_QTY_EPSILON
                || empty($snap['exchange_rate'])
            ) {
                return null;
            }

            $liveMvalue = (float) $item['market_value_in_account_currency'];
            $snapMvalue = $quantity * (float) $snap['price'] / (float) $snap['exchange_rate'];

            $sums['mvalue'] += $snapMvalue;
            $sums['cost'] += (float) ($item['cost2_in_account_currency'] ?? 0);
            $sums['change'] += (float) ($item['overall_change2_in_account_currency'] ?? 0)
                - $liveMvalue + $snapMvalue;
            $priced++;
        }

        return $priced === count($snapPositions) ? $sums : null;
    }

    private static function _positionsOf(array $items): array
    {
        $positions = [];

        foreach ($items as $symbol => $item) {
            if (!isset($item['market_value_in_account_currency'], $item['price'],
                $item['exchange_rate'])
            ) {
                continue;
            }

            $positions[$symbol] = [
                'quantity' => (float) $item['quantity'],
                'price' => (float) $item['price'],
                'exchange_rate' => (float) $item['exchange_rate'],
            ];
        }

        return $positions;
    }

    private static function _put(int $userId, array $snapshot): void
    {
        try {
            Storage::disk('local')->put(self::path($userId), json_encode($snapshot));
        } catch (\Throwable $e) {
            Log::warning("Positions snapshot for user {$userId} not written: " . $e->getMessage());
        }
    }
}

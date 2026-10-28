<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;

/**
 * Loads what TierReadingAsOf needs for one symbol and day (the stored tier history of the symbol and of VUSA.AS around
 * the day, the symbol's closes two years back) and rebuilds the reading and its warnings: a manual trade dated in the
 * past, and the backfill of the orders and trades made before readings were saved. Read-only.
 */
class RebuiltTierReadings
{
    // Calendar days of closes behind the day: the 2Y near-peak window and a little slack.
    private const CLOSES_DAYS = 740;

    private ?TierReadingAsOf $_asOf = null;

    // preload(): the user, their whole history and the closes read so far (symbol => date => close), from _from on.
    private ?int $_user = null;

    private array $_history = [];

    private array $_closes = [];

    private string $_from = '';

    /**
     * A check in OrderTierCheck's shape: source (rebuilt or none), as_of, taken_at, reading (with its facts), warnings
     * (none before the stored history) and verdict.
     */
    public function check(int $userId, string $symbol, string $action, string $day): array
    {
        $read     = $this->read($userId, $symbol, $day);
        $none     = $read['source'] === TierReadingAsOf::SOURCE_NONE;
        $warnings = $none ? [] : OrderTierWarnings::fromConfig()->evaluate($action, $read['reading']['facts']);

        return [
            'source'             => $read['source'],
            'as_of'              => $read['as_of'] ?? $day,
            'taken_at'           => Carbon::now()->format('Y-m-d H:i:s'),
            'reading'            => $read['reading'],
            'warnings'           => $warnings,
            'verdict'            => $none ? OrderTierWarnings::VERDICT_NONE : OrderTierWarnings::verdict($warnings),
            'position_pct_after' => null,
        ];
    }

    /**
     * Loads the user's whole stored tier history once, and keeps each symbol's closes from $from on as they are first
     * read, for many readings in a row (the backfill).
     */
    public function preload(int $userId, string $from): void
    {
        $this->_user    = $userId;
        $this->_history = (new TierHistoryRepository())->loadAll($userId);
        $this->_from    = Carbon::parse($from)->subDays(self::CLOSES_DAYS)->format('Y-m-d');
        $this->_closes  = [];
    }

    /**
     * @return array{source: string, as_of: ?string, reading: array} TierReadingAsOf::read()
     */
    public function read(int $userId, string $symbol, string $day): array
    {
        $benchmark = TierCalculationService::BENCHMARK_SYMBOL;
        $asOf      = $this->_asOf ??= TierReadingAsOf::fromConfig();
        if ($this->_user === $userId) {
            $this->_closes[$symbol] ??= StoredCloses::load($symbol, $this->_from)['prices'];

            return $asOf->read($symbol, $day, $this->_history[$symbol] ?? [], $this->_history[$benchmark] ?? [],
                $this->_closes[$symbol]);
        }

        $lookback = (int) config('myfinance2.watchlistsymbols.tier_history.lookback_days', 365);
        // The rows of [day - lookback - a week, day]: the day's own row (or the last one before it) and its window.
        $history = (new TierHistoryRepository())->loadWindow(
            $userId, Carbon::parse($day)->addDay(), $lookback + 8, [$symbol, $benchmark]
        );
        $closes = StoredCloses::load($symbol, Carbon::parse($day)->subDays(self::CLOSES_DAYS)->format('Y-m-d'));

        return $asOf->read($symbol, $day, $history[$symbol] ?? [], $history[$benchmark] ?? [], $closes['prices']);
    }
}

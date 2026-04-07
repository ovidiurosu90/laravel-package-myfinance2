<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use ovidiuro\myfinance2\Mail\PriceAlertTriggered;
use ovidiuro\myfinance2\App\Models\Currency;
use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\PriceAlertNotification;
use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Models\StatToday;
use ovidiuro\myfinance2\App\Models\Trade;
use ovidiuro\myfinance2\App\Models\Scopes\AssignedToUserScope;

/**
 * Alert evaluation engine.
 *
 * evaluateAlerts() runs inside the minutely finance-api-cron on a configurable
 * interval (default: every 5 min) with a 20-second execution budget.
 * Heavy work (Yahoo Finance historical fetches) belongs in the suggestion engine,
 * NOT here.
 */
class AlertService
{
    /** Per-instance EUR rate cache: currency+date → float|null */
    private array $_eurRateCache = [];

    /** Per-instance open position cache: userId:symbol → array|null */
    private array $_positionCache = [];

    /**
     * Evaluate active alerts for a user.
     * Returns stats: processed, triggered, skipped, deferred, time_ms.
     *
     * @param int $userId
     *
     * @return array
     */
    public function evaluateAlerts(int $userId): array
    {
        $stats = ['processed' => 0, 'triggered' => 0, 'skipped' => 0, 'deferred' => 0, 'time_ms' => 0];
        $startTime = microtime(true);
        $maxSeconds = (int) config('alerts.eval_max_seconds', 20);

        $notifiedToday = $this->_getNotifiedTodaySymbols($userId);

        $activeAlerts = PriceAlert::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('status', 'ACTIVE')
            ->with('tradeCurrencyModel')
            ->get();

        if ($activeAlerts->isEmpty()) {
            return $stats;
        }

        // Warm the quote cache for all alert symbols in one batch
        $symbols = $activeAlerts->pluck('symbol')->unique()->values()->toArray();
        $quotes = $this->_getQuotes($symbols);

        foreach ($activeAlerts as $alert) {
            $stats['processed']++;

            if ((microtime(true) - $startTime) > $maxSeconds) {
                $remaining = $activeAlerts->count() - $stats['processed'] + 1;
                $stats['deferred'] += $remaining;
                Log::info("AlertService: budget exceeded ({$maxSeconds}s), deferring {$remaining} alerts");
                break;
            }

            if (in_array($alert->symbol, $notifiedToday, true)) {
                $stats['skipped']++;
                continue;
            }

            if (!$alert->canFire()) {
                $stats['skipped']++;
                continue;
            }

            $currentPrice = isset($quotes[$alert->symbol])
                ? (float) $quotes[$alert->symbol]['price']
                : null;

            if ($currentPrice === null) {
                $stats['skipped']++;
                continue;
            }

            if (config('alerts.market_hours_only', false)) {
                $marketUtils = $quotes[$alert->symbol]['marketUtils'] ?? null;
                if ($marketUtils) {
                    try {
                        $status = $marketUtils->getMarketStatus()['status'] ?? 'UNKNOWN';
                        if ($status !== 'OPEN') {
                            $stats['skipped']++;
                            continue;
                        }
                    } catch (\Throwable $e) {
                        Log::warning(
                            "AlertService: market status check failed for {$alert->symbol}: "
                            . $e->getMessage()
                        );
                    }
                }
            }

            if ($this->_isPotentialSplitAnomaly($alert, $currentPrice)) {
                $sent = $this->_sendSplitWarningEmail($alert, $currentPrice, $userId);
                if ($sent) {
                    $notifiedToday[] = $alert->symbol;
                }
                $stats['skipped']++;
                continue;
            }

            $triggered = match ($alert->alert_type) {
                'PRICE_ABOVE' => $currentPrice >= (float) $alert->target_price,
                'PRICE_BELOW' => $currentPrice <= (float) $alert->target_price,
                default       => false,
            };

            if (!$triggered) {
                $stats['skipped']++;
                continue;
            }

            $tradeCurrency = $alert->tradeCurrencyModel?->iso_code ?? '';
            $projectedGain = $this->_calculateProjectedGainForUser($userId, $alert, $currentPrice, $tradeCurrency);

            $sent = $this->_sendAlertEmail($alert, $currentPrice, $projectedGain, $userId);

            if ($sent) {
                $alert->increment('trigger_count');
                $alert->update(['last_triggered_at' => now()]);
                $notifiedToday[] = $alert->symbol;
                $stats['triggered']++;
            }
        }

        $stats['time_ms'] = (int) ((microtime(true) - $startTime) * 1000);
        return $stats;
    }

    /**
     * Determine the lookback window (in years) for a user's position in a symbol.
     * Returns 2 if the position has been held for 2+ years, 1 otherwise.
     *
     * @param int    $userId
     * @param string $symbol
     *
     * @return int
     */
    public function getLookbackYears(int $userId, string $symbol): int
    {
        $earliestTimestamp = Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('symbol', $symbol)
            ->where('status', 'OPEN')
            ->where('action', 'BUY')
            ->orderBy('timestamp', 'ASC')
            ->value('timestamp');

        if ($earliestTimestamp === null) {
            return 1;
        }

        $heldDays = (int) ((time() - strtotime((string) $earliestTimestamp)) / 86400);
        return $heldDays >= 730 ? 2 : 1;
    }

    /**
     * Auto-generate PRICE_ABOVE alert suggestions from a user's open positions.
     *
     * Strategy: for each open BUY position, if no ACTIVE/PAUSED PRICE_ABOVE alert
     * already exists for the symbol, suggest a target $thresholdPct% below the
     * lookback high (2Y if held 2+ years, 1Y otherwise), provided the target is
     * above the current market price.
     *
     * Returns: ['created' => int, 'skipped' => int, 'symbols' => string[], 'dry_run' => bool]
     *
     * @param int        $userId
     * @param bool       $dryRun        When true, compute suggestions but do not insert records
     * @param float|null $thresholdPct  Override config suggestion_threshold_pct
     * @param array|null $filterSymbols When set, only process these symbols
     *
     * @return array
     */
    public function suggestAlerts(
        int $userId,
        bool $dryRun = false,
        ?float $thresholdPct = null,
        ?array $filterSymbols = null
    ): array
    {
        $stats = ['created' => 0, 'skipped' => 0, 'symbols' => [], 'created_ids' => [], 'dry_run' => $dryRun];

        $openSymbols = $this->_getOpenSymbolsForUser($userId);

        if (!empty($filterSymbols)) {
            $openSymbols = array_values(array_intersect($openSymbols, $filterSymbols));
        }

        if (empty($openSymbols)) {
            return $stats;
        }

        $quotes = $this->_getQuotes($openSymbols);
        $existingAlertKeys = $this->_getExistingAlertSymbols($userId);
        $currencyMap      = $this->_getCurrencyMapForUser($userId, $openSymbols);
        $currencyIds      = array_values(array_filter(array_unique(array_values($currencyMap))));
        $currencyDisplayCodes = Currency::whereIn('id', $currencyIds)
            ->get(['id', 'display_code'])
            ->mapWithKeys(fn($c) => [$c->id => html_entity_decode(strip_tags($c->display_code), ENT_QUOTES | ENT_HTML5, 'UTF-8')])
            ->toArray();
        $threshold = $thresholdPct ?? (float) config('alerts.suggestion_threshold_pct', 3);
        $now = now();

        foreach ($openSymbols as $symbol) {
            $dedupeKey = "{$symbol}:PRICE_ABOVE";

            if (in_array($dedupeKey, $existingAlertKeys, true)) {
                $stats['skipped']++;
                continue;
            }

            if (!isset($quotes[$symbol])) {
                $stats['skipped']++;
                continue;
            }

            $quote = $quotes[$symbol];
            $currentPrice = (float) ($quote['price'] ?? 0);

            if ($currentPrice <= 0) {
                $stats['skipped']++;
                continue;
            }

            $lookbackYears   = $this->getLookbackYears($userId, $symbol);
            $historicalHigh  = $this->_getHistoricalHigh($symbol, $lookbackYears);
            $highValue       = $historicalHigh['value'];
            $highDate        = $historicalHigh['date'];

            if ($highValue === null || $highValue <= 0) {
                $stats['skipped']++;
                continue;
            }

            $targetPrice = round($highValue * (1.0 - $threshold / 100.0), 4);

            if ($targetPrice <= $currentPrice) {
                $stats['skipped']++;
                continue;
            }

            $stats['symbols'][] = $symbol;

            if ($dryRun) {
                $stats['created']++;
                continue;
            }

            $currencyId          = $currencyMap[$symbol] ?? null;
            $currencyDisplayCode = $currencyId ? ($currencyDisplayCodes[$currencyId] ?? '') : '';
            $lookbackLabel       = $lookbackYears . 'Y';

            $notes = round($threshold, 1) . '% below ' . $lookbackLabel . ' high of '
                . MoneyFormat::get_formatted_price($highValue);
            if ($currencyDisplayCode) {
                $notes .= ' ' . $currencyDisplayCode;
            }
            if ($highDate) {
                $notes .= ' on ' . $highDate;
            }

            $newId = DB::connection(config('myfinance2.db_connection'))
                ->table('price_alerts')
                ->insertGetId([
                    'user_id'              => $userId,
                    'symbol'               => $symbol,
                    'alert_type'           => 'PRICE_ABOVE',
                    'target_price'         => $targetPrice,
                    'trade_currency_id'    => $currencyMap[$symbol] ?? null,
                    'status'               => 'ACTIVE',
                    'source'               => 'suggestion_high',
                    'notification_channel' => 'email',
                    'notes'                => $notes,
                    'trigger_count'        => 0,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]);
            $stats['created_ids'][] = $newId;

            $stats['created']++;
        }

        return $stats;
    }

    /**
     * Get all user IDs that have at least one ACTIVE price alert.
     *
     * @return array
     */
    public function getAllUserIdsWithActiveAlerts(): array
    {
        return PriceAlert::withoutGlobalScope(AssignedToUserScope::class)
            ->where('status', 'ACTIVE')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id')
            ->toArray();
    }

    /**
     * Calculate projected gain/loss if selling at $currentPrice for a specific user's position.
     * Used for email notifications (triggered at current market price).
     * Returns: ['gain_value' => float, 'gain_pct' => float, 'gain_eur' => float|null,
     *            'avg_cost' => float, 'total_qty' => float, 'trade_currency' => string,
     *            'has_position' => bool]
     *
     * @param int       $userId
     * @param PriceAlert $alert
     * @param float     $currentPrice
     * @param string    $tradeCurrency ISO code
     *
     * @return array|null
     */
    private function _calculateProjectedGainForUser(
        int $userId,
        PriceAlert $alert,
        float $currentPrice,
        string $tradeCurrency
    ): ?array
    {
        $position = $this->_getOpenPosition($userId, $alert->symbol);

        if ($position === null || $position['total_qty'] <= 0) {
            return null;
        }

        $avgCost = $position['avg_cost'];
        $totalQty = $position['total_qty'];
        $targetPrice = (float) $alert->target_price;

        $gainPerUnit = $currentPrice - $avgCost;
        $totalGain = $gainPerUnit * $totalQty;
        $gainPct = $avgCost > 0 ? ($gainPerUnit / $avgCost) * 100.0 : 0.0;

        $gainEur = null;
        $eurRate = $this->_getEurRate($tradeCurrency);
        if ($eurRate !== null) {
            $gainEur = round($totalGain * $eurRate, 2);
        }

        return [
            'gain_value'     => round($totalGain, 2),
            'gain_pct'       => round($gainPct, 4),
            'gain_eur'       => $gainEur,
            'avg_cost'       => $avgCost,
            'total_qty'      => $totalQty,
            'trade_currency' => $tradeCurrency,
            'has_position'   => true,
        ];
    }

    /**
     * Fetch the highest daily high price over the past $lookbackYears years for a symbol.
     * Uses Yahoo Finance API (split-adjusted). No execution budget — for ad-hoc use only.
     * Returns ['value' => float|null, 'date' => string|null (Y-m-d)].
     *
     * @param string $symbol
     * @param int    $lookbackYears
     *
     * @return array
     */
    private function _getHistoricalHigh(string $symbol, int $lookbackYears): array
    {
        $financeApi = new FinanceAPI();
        $quote = $financeApi->getQuote($symbol, true, false);

        if ($quote === null) {
            return ['value' => null, 'date' => null];
        }

        $endDate   = new \DateTime('today');
        $startDate = new \DateTime("-{$lookbackYears} years");

        $historicalData = $financeApi->getHistoricalPeriodQuoteData($quote, $startDate, $endDate);

        if (empty($historicalData)) {
            return ['value' => null, 'date' => null];
        }

        $high     = null;
        $highDate = null;
        foreach ($historicalData as $dayData) {
            $dayHigh = (float) $dayData->getHigh();
            if ($dayHigh > 0 && ($high === null || $dayHigh > $high)) {
                $high     = $dayHigh;
                $highDate = $dayData->getDate()?->format('Y-m-d');
            }
        }

        return ['value' => $high, 'date' => $highDate];
    }

    /**
     * Get distinct open-position symbols (OPEN BUY trades) for a user.
     *
     * @param int $userId
     *
     * @return array
     */
    private function _getOpenSymbolsForUser(int $userId): array
    {
        return Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('status', 'OPEN')
            ->where('action', 'BUY')
            ->distinct()
            ->pluck('symbol')
            ->toArray();
    }

    /**
     * Get "symbol:alert_type" keys for existing ACTIVE or PAUSED alerts for a user.
     * Used to skip duplicate suggestion generation.
     *
     * @param int $userId
     *
     * @return array
     */
    private function _getExistingAlertSymbols(int $userId): array
    {
        return PriceAlert::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->whereIn('status', ['ACTIVE', 'PAUSED'])
            ->get(['symbol', 'alert_type'])
            ->map(fn ($a) => "{$a->symbol}:{$a->alert_type}")
            ->toArray();
    }

    /**
     * Get a symbol → trade_currency_id map for a user's open positions.
     *
     * @param int   $userId
     * @param array $symbols
     *
     * @return array
     */
    private function _getCurrencyMapForUser(int $userId, array $symbols): array
    {
        return Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('status', 'OPEN')
            ->where('action', 'BUY')
            ->whereIn('symbol', $symbols)
            ->select(['symbol', 'trade_currency_id'])
            ->get()
            ->pluck('trade_currency_id', 'symbol')
            ->toArray();
    }

    /**
     * Get symbols for which the user has already received a notification today.
     *
     * @param int $userId
     *
     * @return array
     */
    private function _getNotifiedTodaySymbols(int $userId): array
    {
        return PriceAlertNotification::where('user_id', $userId)
            ->where('status', 'SENT')
            ->where('sent_at', '>=', now()->startOfDay())
            ->pluck('symbol')
            ->unique()
            ->toArray();
    }

    /**
     * Detect potential stock-split anomaly: target price is suspiciously far from the
     * current price — beyond the smallest known split ratio — suggesting the alert was
     * set before an unapplied split.
     *
     * Uses a threshold (not a proximity match) so that any ratio ≥ min(SPLIT_RATIOS)
     * is flagged, not just values close to a specific split factor.
     *
     * @param PriceAlert $alert
     * @param float      $currentPrice
     *
     * @return bool
     */
    private function _isPotentialSplitAnomaly(PriceAlert $alert, float $currentPrice): bool
    {
        return SplitDetectionService::isAlertTargetStale(
            $alert->alert_type,
            (float) $alert->target_price,
            $currentPrice
        );
    }

    /**
     * Send the price alert triggered email.
     *
     * @param PriceAlert $alert
     * @param float      $currentPrice
     * @param array|null $projectedGain
     * @param int        $userId
     *
     * @return bool
     */
    private function _sendAlertEmail(
        PriceAlert $alert,
        float $currentPrice,
        ?array $projectedGain,
        int $userId
    ): bool
    {
        $emailTo = config('alerts.email_to') ?: $this->_getUserEmail($userId);

        if (empty($emailTo)) {
            Log::warning("AlertService: no email address for user {$userId}, skipping alert #{$alert->id}");
            return false;
        }

        $notification = $this->_createNotificationRecord($alert, $currentPrice, $projectedGain, $userId, 'SENT');

        try {
            $mailable = new PriceAlertTriggered($alert, $currentPrice, $projectedGain);
            Mail::to($emailTo)->send($mailable);
        } catch (\Throwable $e) {
            Log::error("AlertService: email send failed for alert #{$alert->id}: " . $e->getMessage());
            $notification->update(['status' => 'FAILED', 'error_message' => substr($e->getMessage(), 0, 500)]);
            return false;
        }

        Log::info("AlertService: alert #{$alert->id} triggered for {$alert->symbol} → email sent to {$emailTo}");
        return true;
    }

    /**
     * Send a split-anomaly maintenance warning email.
     * Creates a notification record so the 1-per-day throttle applies to split warnings too.
     *
     * @param PriceAlert $alert
     * @param float      $currentPrice
     * @param int        $userId
     *
     * @return bool
     */
    private function _sendSplitWarningEmail(PriceAlert $alert, float $currentPrice, int $userId): bool
    {
        $emailTo = config('alerts.email_to') ?: $this->_getUserEmail($userId);

        if (empty($emailTo)) {
            return false;
        }

        $notification = $this->_createNotificationRecord($alert, $currentPrice, null, $userId, 'SENT');

        try {
            $mailable = new PriceAlertTriggered($alert, $currentPrice, null, true);
            Mail::to($emailTo)->send($mailable);
            Log::warning("AlertService: split anomaly detected for alert #{$alert->id} ({$alert->symbol})"
                . " — maintenance email sent");
            return true;
        } catch (\Throwable $e) {
            Log::error("AlertService: split warning email failed for alert #{$alert->id}: " . $e->getMessage());
            $notification->update(['status' => 'FAILED', 'error_message' => substr($e->getMessage(), 0, 500)]);
            return false;
        }
    }

    /**
     * Create a PriceAlertNotification log record.
     *
     * @param PriceAlert $alert
     * @param float      $currentPrice
     * @param array|null $projectedGain
     * @param int        $userId
     * @param string     $status SENT|FAILED
     *
     * @return PriceAlertNotification
     */
    private function _createNotificationRecord(
        PriceAlert $alert,
        float $currentPrice,
        ?array $projectedGain,
        int $userId,
        string $status
    ): PriceAlertNotification
    {
        return PriceAlertNotification::create([
            'price_alert_id'     => $alert->id,
            'user_id'            => $userId,
            'symbol'             => $alert->symbol,
            'notification_channel' => $alert->notification_channel,
            'current_price'      => $currentPrice,
            'target_price'       => $alert->target_price,
            'alert_type'         => $alert->alert_type,
            'projected_gain_eur' => $projectedGain['gain_eur'] ?? null,
            'projected_gain_pct' => $projectedGain['gain_pct'] ?? null,
            'sent_at'            => now(),
            'status'             => $status,
        ]);
    }

    /**
     * Get current quotes for an array of symbols, reusing FinanceAPI 2-min cache.
     * Returns: symbol => ['price' => float, ...]
     *
     * @param array $symbols
     *
     * @return array
     */
    private function _getQuotes(array $symbols): array
    {
        if (empty($symbols)) {
            return [];
        }

        $financeUtils = new FinanceUtils();
        $quotes = $financeUtils->getQuotes($symbols, null, false);

        return is_array($quotes) ? $quotes : [];
    }

    /**
     * Get open position for a symbol (cached per user+symbol within this evaluation pass).
     * Returns: ['avg_cost' => float, 'total_qty' => float] or null.
     *
     * @param int    $userId
     * @param string $symbol
     *
     * @return array|null
     */
    private function _getOpenPosition(int $userId, string $symbol): ?array
    {
        $cacheKey = "{$userId}:{$symbol}";

        if (array_key_exists($cacheKey, $this->_positionCache)) {
            return $this->_positionCache[$cacheKey];
        }

        $trades = Trade::withoutGlobalScope(AssignedToUserScope::class)
            ->where('user_id', $userId)
            ->where('symbol', $symbol)
            ->where('status', 'OPEN')
            ->where('action', 'BUY')
            ->get();

        if ($trades->isEmpty()) {
            $this->_positionCache[$cacheKey] = null;
            return null;
        }

        $totalQty = 0.0;
        $totalCost = 0.0;
        foreach ($trades as $trade) {
            $qty = (float) $trade->quantity;
            $totalQty += $qty;
            $totalCost += $qty * (float) $trade->unit_price;
        }

        $position = $totalQty > 0
            ? ['avg_cost' => $totalCost / $totalQty, 'total_qty' => $totalQty]
            : null;

        $this->_positionCache[$cacheKey] = $position;
        return $position;
    }

    /**
     * Get EUR multiplier for a trade currency (cached within this instance).
     * Returns null if the currency is not supported or rate is unavailable.
     *
     * @param string $tradeCurrency ISO code
     *
     * @return float|null
     */
    private function _getEurRate(string $tradeCurrency): ?float
    {
        if ($tradeCurrency === 'EUR') {
            return 1.0;
        }

        $cacheKey = $tradeCurrency . ':' . date('Y-m-d');

        if (array_key_exists($cacheKey, $this->_eurRateCache)) {
            return $this->_eurRateCache[$cacheKey];
        }

        $exchangeMap = [
            'USD' => ['symbol' => 'EURUSD=X', 'scale' => 1],
            'GBP' => ['symbol' => 'EURGBP=X', 'scale' => 1],
            'GBp' => ['symbol' => 'EURGBP=X', 'scale' => 100],
            'GBX' => ['symbol' => 'EURGBP=X', 'scale' => 100],
        ];

        if (!isset($exchangeMap[$tradeCurrency])) {
            $this->_eurRateCache[$cacheKey] = null;
            return null;
        }

        $cfg = $exchangeMap[$tradeCurrency];
        $rate = $this->_fetchEurRate($cfg['symbol'], $cfg['scale']);

        $this->_eurRateCache[$cacheKey] = $rate;
        return $rate;
    }

    /**
     * Fetch EUR rate from stats tables.
     *
     * @param string $symbol Exchange rate symbol (e.g. EURUSD=X)
     * @param int    $scale  Scaling factor (100 for GBX pence)
     *
     * @return float|null
     */
    private function _fetchEurRate(string $symbol, int $scale): ?float
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

    /**
     * Get the email address for a user.
     *
     * @param int $userId
     *
     * @return string|null
     */
    private function _getUserEmail(int $userId): ?string
    {
        $user = User::find($userId);
        return $user?->email;
    }
}

<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Returns;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use ovidiuro\myfinance2\App\Services\AlertMailer;
use ovidiuro\myfinance2\Mail\ReturnsMissingQuotes;

/**
 * Emails the account owner when the returns refresh cron values held positions at 0 for lack of a price.
 *
 * The hourly `app:finance-api-cron --refresh-returns` recomputes the current year every run and each past
 * year when its 4-week cache expires, which is exactly when a price the finance API dropped (typically a
 * delisted symbol) starts showing as 0 on the returns page. collect() gathers the gaps of every year the
 * run recomputed (the same ReturnsMissingQuoteAlerts the page shows); send() then emails each owner once
 * for all of them and logs a WARNING (which also reaches the logs:email-daily-errors digest).
 *
 * A year is emailed again only when its gaps change, or on a later day while they persist, so the
 * current year (recomputed hourly) yields at most one email a day.
 */
class ReturnsMissingQuoteNotifier
{
    private const STATE_KEY_PREFIX = 'returns_missing_quotes_email_';
    private const STATE_TTL_SECONDS = 35 * 86400; // outlives the 4-week past-year refresh cycle

    /** @var array<int, array> year => alerts from ReturnsMissingQuoteAlerts */
    private array $_alertsByYear = [];

    public function collect(int $year, array $returnsData): void
    {
        $alerts = (new ReturnsMissingQuoteAlerts())->check($returnsData, $year);
        if (empty($alerts)) {
            return;
        }

        $this->_alertsByYear[$year] = $alerts;

        $positions = array_merge(...array_column($alerts, 'positions'));
        $described = array_map(
            fn($p) => "{$p['symbol']} ({$p['account_name']}, {$p['date']})",
            $positions
        );
        Log::warning("Returns $year: " . count($positions) . ' held position(s) valued at 0 because no price '
            . 'was found: ' . implode(', ', $described) . '. Add trades.price_overrides (and '
            . 'delisted_symbols if the symbol was delisted).');
    }

    /**
     * Email each owner about the years whose gaps are new or not yet reported today
     */
    public function send(): void
    {
        foreach ($this->_groupByUser() as $userId => $years) {
            $due = [];
            $states = [];
            foreach ($years as $year => $positions) {
                $state = ['signature' => $this->_signature($positions), 'date' => date('Y-m-d')];
                if (Cache::get($this->_stateKey($userId, $year)) !== $state) {
                    $due[$year] = $positions;
                    $states[$year] = $state;
                }
            }

            if (!empty($due) && $this->_email($userId, $due)) {
                foreach ($states as $year => $state) {
                    Cache::put($this->_stateKey($userId, $year), $state, self::STATE_TTL_SECONDS);
                }
            }
        }
    }

    /**
     * @return array<int, array<int, array>> userId => year => positions
     */
    private function _groupByUser(): array
    {
        $grouped = [];
        foreach ($this->_alertsByYear as $year => $alerts) {
            foreach ($alerts as $alert) {
                foreach ($alert['positions'] as $position) {
                    if ($position['user_id'] !== null) {
                        $grouped[(int) $position['user_id']][$year][] = $position;
                    }
                }
            }
        }
        ksort($grouped);

        return $grouped;
    }

    private function _signature(array $positions): string
    {
        return md5(json_encode(array_map(
            fn($p) => [$p['symbol'], $p['account_id'], $p['date'], $p['quantity']],
            $positions
        )));
    }

    private function _stateKey(int $userId, int $year): string
    {
        return self::STATE_KEY_PREFIX . $userId . '_' . $year;
    }

    /**
     * Protected so tests can capture the email instead of sending it
     */
    protected function _email(int $userId, array $positionsByYear): bool
    {
        $emailTo = config('alerts.email_to') ?: User::find($userId)?->email;
        if (empty($emailTo)) {
            Log::warning("ReturnsMissingQuoteNotifier: no email address for user #$userId");
            return false;
        }

        try {
            AlertMailer::send($emailTo, new ReturnsMissingQuotes($positionsByYear), 'ReturnsMissingQuoteNotifier');
            return true;
        } catch (\Throwable $e) {
            Log::error("ReturnsMissingQuoteNotifier: email to user #$userId failed: " . $e->getMessage());
            return false;
        }
    }
}

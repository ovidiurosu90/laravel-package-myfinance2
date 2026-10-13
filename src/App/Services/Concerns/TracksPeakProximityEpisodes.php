<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services\Concerns;

use Illuminate\Database\Eloquent\Builder;

use ovidiuro\myfinance2\App\Models\PeakProximityAlertEvent;

/**
 * The episode bookkeeping shared by the peak-proximity alerts that are not near-peak (range-bound, weak tier in
 * profit, tier signals): one OPEN event per user, symbol and signal, the same-day dismissal guard and the position
 * figures every inbox card snapshot carries.
 */
trait TracksPeakProximityEpisodes
{
    /**
     * Whether the user dismissed this symbol's alert for the same signal earlier today, with none open: a same-day
     * re-run does not pop a dismissed card back into the inbox. The next day opens a fresh one.
     */
    private function _dismissedToday(int $userId, string $symbol, string $signal): bool
    {
        if ($this->_openEpisode($userId, $symbol, $signal) !== null) {
            return false;
        }

        return $this->_episodeQuery($userId, $symbol, $signal)
            ->where('status', PeakProximityAlertEvent::STATUS_DISMISSED)
            ->where('dismissed_at', '>=', now()->startOfDay())
            ->exists();
    }

    /**
     * The OPEN episode for this symbol and signal created with $snapshot, or refreshed with it.
     */
    private function _saveEpisode(
        int $userId,
        string $symbol,
        string $signal,
        array $snapshot
    ): PeakProximityAlertEvent
    {
        $event = $this->_openEpisode($userId, $symbol, $signal);
        if ($event === null) {
            return PeakProximityAlertEvent::create(array_merge($snapshot, [
                'user_id'     => $userId,
                'symbol'      => $symbol,
                'signal'      => $signal,
                'status'      => PeakProximityAlertEvent::STATUS_OPEN,
                'opened_at'   => now(),
                'email_count' => 0,
            ]));
        }
        $event->fill($snapshot)->save();

        return $event;
    }

    private function _openEpisode(int $userId, string $symbol, string $signal): ?PeakProximityAlertEvent
    {
        return $this->_episodeQuery($userId, $symbol, $signal)
            ->where('status', PeakProximityAlertEvent::STATUS_OPEN)
            ->first();
    }

    private function _episodeQuery(int $userId, string $symbol, string $signal): Builder
    {
        return PeakProximityAlertEvent::where('user_id', $userId)
            ->where('symbol', $symbol)
            ->where('signal', $signal);
    }

    /**
     * The summary fields every inbox card shows without live quotes: the trade currency, the price, the open
     * position's unrealized and today's gain, and the superstar star.
     */
    private function _positionSnapshot(array $quoteData): array
    {
        return [
            'currency'            => isset($quoteData['tradeCurrencyModel']->display_code)
                ? html_entity_decode(
                    (string) $quoteData['tradeCurrencyModel']->display_code, ENT_QUOTES | ENT_HTML5, 'UTF-8'
                )
                : '€',
            'price'               => isset($quoteData['price']) ? (float) $quoteData['price'] : null,
            'unrealized_gain_eur' => $quoteData['unrealized_gain_eur'] ?? null,
            'unrealized_gain_pct' => $quoteData['unrealized_gain_pct'] ?? null,
            'today_gain_eur'      => $quoteData['today_gain_eur'] ?? null,
            'today_gain_pct'      => $quoteData['today_gain_pct'] ?? null,
            'superstar'           => $quoteData['categorization']['superstar'] ?? null,
        ];
    }
}

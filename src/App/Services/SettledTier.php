<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * Immutable result of SettledTierResolver for one symbol on one day: the settled tier, the
 * time-in-tier shares behind it, and the tier signal (if any) from the gap between today's tier and
 * the settled tier. Severity and caution flags need portfolio context and are added by a presenter.
 */
final class SettledTier
{
    public function __construct(
        public readonly ?string $settledTier,
        // The basis value at the consistency percentile (the median with the default 50).
        public readonly ?float  $settledValue,
        public readonly string  $status,
        // Lookback rows behind the figures, and their first and last date (Y-m-d).
        public readonly int     $days,
        public readonly ?string $firstDate,
        public readonly ?string $lastDate,
        // tier => % of lookback days, Platinum first; sums to 100 when $days > 0.
        public readonly array   $shares,
        // % of lookback days at or above / at or below today's tier. Null without a today tier.
        public readonly ?float  $atOrAboveTodayPct,
        public readonly ?float  $atOrBelowTodayPct,
        public readonly ?string $signal,
        public readonly ?string $action,
        // False for a chip-only signal (a rare high or a deterioration on a watchlist-only symbol).
        public readonly bool    $alertable,
        // Trading days the current two-tier drop has lasted (including today), when there is one.
        public readonly int     $deterioratingDays,
        // First lookback day the symbol was owned, when it switched from watchlist to owned inside it.
        public readonly ?string $ownedSince,
        public readonly string  $explanation,
        // SettledTierResolver::MEASURE_* the history was read in (null: each day's deciding value), and
        // whether today's own measure fell back to the market history for lack of own days.
        public readonly ?string $measure = null,
        public readonly bool    $measureFallback = false,
        // date => value behind the shares (the chosen measure), for the per-day ribbon.
        public readonly array   $series = [],
        // A deterioration only: the settled tier the drop is measured from ($settledTier then follows today).
        public readonly ?string $dropFrom = null
    )
    {
    }

    public function toArray(): array
    {
        return [
            'settled_tier'         => $this->settledTier,
            'settled_value'        => $this->settledValue,
            'status'               => $this->status,
            'days'                 => $this->days,
            'first_date'           => $this->firstDate,
            'last_date'            => $this->lastDate,
            'shares'               => $this->shares,
            'at_or_above_today_pct' => $this->atOrAboveTodayPct,
            'at_or_below_today_pct' => $this->atOrBelowTodayPct,
            'signal'               => $this->signal,
            'action'               => $this->action,
            'alertable'            => $this->alertable,
            'deteriorating_days'   => $this->deterioratingDays,
            'owned_since'          => $this->ownedSince,
            'explanation'          => $this->explanation,
            'measure'              => $this->measure,
            'measure_fallback'     => $this->measureFallback,
            'drop_from'            => $this->dropFrom,
        ];
    }
}

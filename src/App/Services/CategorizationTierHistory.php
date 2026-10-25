<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Carbon\Carbon;

/**
 * The tier-history keys CategorizationService adds to each symbol's entry. Loads the user's lookback
 * window once (one query) and resolves each symbol against it:
 *
 *   today_tier          the computed tier shown today (today's reading, without override)
 *   settled_tier        the tier held or beaten on most days of the last 12 months (null when the
 *                       history is too short)
 *   tier_history        view data for the time-in-tier bar and the per-day ribbon (null without rows)
 *   tier_signal         SettledTier::toArray(): status, shares, signal and action
 *   settled_explanation one sentence on how the settled tier was reached
 *   effective_tier      the tier every consumer reads (badge, health card, alerts): see effectiveTier()
 *   tier_tooltip        settled explanation followed by the basis explanation (the override reason
 *                       alone when overridden)
 *
 * The entry is merged last into the categorization, so effective_tier replaces the decision's.
 */
final class CategorizationTierHistory
{
    private array $_window;
    private SettledTierResolver $_resolver;
    private TierHistoryPresenter $_presenter;

    public function __construct(int $userId, private readonly Carbon $_day)
    {
        $days             = (int) config('watchlistsymbols.tier_history.lookback_days', 365);
        $this->_window    = (new TierHistoryRepository())->loadWindow($userId, $_day, $days);
        $this->_resolver  = SettledTierResolver::fromConfig();
        $this->_presenter = new TierHistoryPresenter(
            (float) config('watchlistsymbols.tier_history.split_share_pct', 15)
        );
    }

    /**
     * The measure deciding today's tier, so the history is read in the same one: the 1Y market return when
     * it decides (every symbol by default), your own return when it does (no usable market figure, or the
     * legacy tier_basis 'own'). Null for an unrated symbol.
     */
    public static function measureOf(string $basis): ?string
    {
        return match ($basis) {
            TierDecision::BASIS_MARKET_MOMENTUM => SettledTierResolver::MEASURE_MARKET,
            TierDecision::BASIS_ANNUALIZED_RETURN,
            TierDecision::BASIS_RAW_RETURN      => SettledTierResolver::MEASURE_OWN,
            default                             => null,
        };
    }

    /**
     * override ?? settled ?? today. A deterioration already makes the settled tier follow today, and
     * with too short a history the settled tier is null, so today's tier is used. Two exceptions keep
     * the decision's tier: the benchmark (pinned to at least Gold by the classifier) and a symbol
     * unrated today (no usable figure now, so an old settled tier is not carried forward).
     */
    public static function effectiveTier(TierDecision $decision, SettledTier $settled): ?string
    {
        if ($decision->overrideTier !== null) {
            return $decision->overrideTier;
        }
        if ($decision->isBenchmark || $decision->computedTier === null) {
            return $decision->tier;
        }

        return $settled->settledTier ?? $decision->computedTier;
    }

    /**
     * The lookback window (symbol => date => stored row), loaded once, for other per-symbol readers.
     */
    public function window(): array
    {
        return $this->_window;
    }

    public function entryFor(string $symbol, TierDecision $decision): array
    {
        $lookback = $this->_window[$symbol] ?? [];
        $settled  = $this->_resolver->resolve(
            $symbol, $decision->computedTier, $lookback, $decision->isOwned, $this->_day->format('Y-m-d'),
            self::measureOf($decision->basis), $decision->basisValue
        );
        $effective = self::effectiveTier($decision, $settled);

        return [
            'effective_tier'      => $effective,
            'today_tier'          => $decision->computedTier,
            'settled_tier'        => $settled->settledTier,
            'tier_history'        => $this->_presenter->forRow($symbol, $lookback, $settled, $this->_day),
            'tier_signal'         => $settled->toArray(),
            'settled_explanation' => $settled->explanation,
            'tier_tooltip'        => self::tierTooltip($decision, $settled),
        ];
    }

    public static function tierTooltip(TierDecision $decision, SettledTier $settled): string
    {
        if ($decision->hasOverride || $decision->isBenchmark) {
            return $decision->explanation;
        }

        return trim($settled->explanation . ' ' . $decision->explanation);
    }
}

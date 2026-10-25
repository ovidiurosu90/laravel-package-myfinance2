<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

/**
 * The near-peak reading: a held position whose price is within its window's threshold of the 3M / 6M / 1Y / 2Y peak.
 * The rule behind the near-peak alert (PeakProximityAlertService) and its chip on the watchlist and the inbox card,
 * so both read the same symbols.
 *
 * A symbol qualifies when you hold it, it is not the benchmark and not read another way: a range-bound symbol is read
 * on its swing (RangeBoundPattern) and a weak tier in profit as TAKE PROFIT (WeakTierProfit, the windows near peak
 * are context there), so one position gets one message. A tier signal does not defer it: both alerts open.
 *
 * Actionable (the alert emails) when a meaningful window (6M / 1Y / 2Y) is near peak and the tier in use is
 * exit-worthy (Rust or Bronze); the chip then shows the alert's action: the tier signal chip's when one shows
 * ("Near peak: SELL", as the email's subject), else the quadrant's ("Near peak: REDUCE"). Otherwise it is
 * context (a strong tier, or the 3M window alone): the chip shows "Near peak" without an action, dimmed.
 * With exit_focused off, any window near peak is actionable.
 *
 * Pure: no database, no config read outside fromConfig().
 */
final class NearPeakReading
{
    public const LABEL = 'Near peak';

    // The quadrant's actions in words; a tier signal's action (SELL, TRIM, ...) points to that chip instead.
    private const ACTION_ADVICE = [
        'ACCUMULATE' => 'strong returns and low volatility',
        'HOLD'       => 'strong returns but high volatility',
        'REDUCE'     => 'low returns and low volatility: consider trimming',
        'EXIT'       => 'low returns and high volatility: consider closing the position',
    ];

    /**
     * @param array<string, float> $_thresholds window => proximity threshold (%), in window order
     * @param string[]             $_meaningful windows that can email on their own
     * @param string[]             $_exitTiers  TierCalculationService constants that are exit-worthy
     */
    public function __construct(
        private readonly array $_thresholds = ['3m' => 2.0, '6m' => 5.0, '1y' => 8.0, '2y' => 10.0],
        private readonly array $_meaningful = ['6m', '1y', '2y'],
        private readonly array $_exitTiers = [TierCalculationService::RUST, TierCalculationService::BRONZE],
        private readonly bool $_exitFocused = true
    )
    {
    }

    /**
     * @param array<string, float>|null $thresholds window => threshold (%), the configured ones when null
     */
    public static function fromConfig(?array $thresholds = null): self
    {
        if ($thresholds === null) {
            $thresholds = [];
            $fallback   = (float) config('alerts.peak_proximity.threshold_pct', 5);
            foreach (config('alerts.peak_proximity.windows', ['3m', '6m', '1y', '2y']) as $window) {
                $thresholds[$window] = (float) config("alerts.peak_proximity.window_thresholds.{$window}", $fallback);
            }
        }

        return new self(
            $thresholds,
            (array) config('alerts.peak_proximity.meaningful_windows', ['6m', '1y', '2y']),
            array_map('strtoupper', (array) config('alerts.peak_proximity.exit_tiers', ['RUST', 'BRONZE'])),
            (bool) config('alerts.peak_proximity.exit_focused', true)
        );
    }

    /**
     * The windows near peak, in window order: window => exit-zone entry (proximity_pct, peak_price_date, ...).
     */
    public function triggeredWindows(array $exitZones): array
    {
        $triggered = [];
        foreach ($this->_thresholds as $window => $threshold) {
            $proximity = $exitZones[$window]['proximity_pct'] ?? null;
            if ($proximity !== null && $proximity >= -$threshold) {
                $triggered[$window] = $exitZones[$window];
            }
        }

        return $triggered;
    }

    /**
     * The meaningful windows among the triggered ones (6M / 1Y / 2Y by default).
     *
     * @return string[]
     */
    public function meaningful(array $triggered): array
    {
        return array_values(array_intersect(array_keys($triggered), $this->_meaningful));
    }

    public function exitWorthy(?string $tier): bool
    {
        return $tier !== null && in_array(strtoupper($tier), $this->_exitTiers, true);
    }

    /**
     * Whether a near-peak reading emails: a meaningful window near peak on an exit-worthy tier (any window when
     * exit_focused is off).
     */
    public function actionable(array $triggered, ?string $tier): bool
    {
        if (!$this->_exitFocused) {
            return !empty($triggered);
        }

        return !empty($this->meaningful($triggered)) && $this->exitWorthy($tier);
    }

    /**
     * The near-peak reading of one symbol, or null when it does not hold.
     *
     * @param array $cat CategorizationService entry: is_owned, is_benchmark, range_bound, take_profit, exit_zones,
     *                   effective_tier, action, tier_signal_chip
     *
     * @return array{label: string, action: ?string, tier: ?string, windows: string[], closest_pct: ?float,
     *     actionable: bool, title: string}|null
     */
    public function detect(array $cat): ?array
    {
        if (empty($cat['is_owned']) || !empty($cat['is_benchmark']) || !empty($cat['range_bound'])
            || !empty($cat['take_profit'])
        ) {
            return null;
        }
        $triggered = $this->triggeredWindows($cat['exit_zones'] ?? []);
        if (empty($triggered)) {
            return null;
        }

        $tier       = $cat['effective_tier'] ?? null;
        $actionable = $this->actionable($triggered, $tier);

        return self::reading(
            array_keys($triggered),
            PeakProximityAlertService::closestProximityPct($triggered),
            $tier,
            $actionable ? (TierSignalChip::shown($cat)['action'] ?? $cat['action'] ?? null) : null,
            $actionable
        );
    }

    /**
     * A reading from its parts: detect() on the dashboard, and the inbox card of an alert stored before its
     * snapshot carried one.
     *
     * @param string[] $windows the windows near peak
     */
    public static function reading(
        array $windows,
        ?float $closestPct,
        ?string $tier,
        ?string $action,
        bool $actionable
    ): array
    {
        $reading = [
            'label'       => self::LABEL,
            'action'      => $action,
            'tier'        => $tier,
            'windows'     => array_values($windows),
            'closest_pct' => $closestPct !== null ? round($closestPct, 2) : null,
            'actionable'  => $actionable,
        ];
        $reading['title'] = self::title($reading);

        return $reading;
    }

    /**
     * The explanation shown in every tooltip: where the price stands, then why it emails or not and what the
     * action means.
     */
    public static function title(array $reading): string
    {
        $windows = implode(', ', array_map('strtoupper', $reading['windows']));
        $text    = self::LABEL . ': the price is near its ' . $windows . ' peak'
            . (count($reading['windows']) === 1 ? '' : 's')
            . ($reading['closest_pct'] !== null
                ? ' (closest ' . TierApproachComparison::formatShort((float) $reading['closest_pct'], '%') . ')'
                : '')
            . '.';
        $tier = $reading['tier'] !== null ? TierCalculationService::tierLabel($reading['tier']) : null;

        if (!$reading['actionable']) {
            return $text . ' ' . ($tier !== null ? $tier . ' tier: ' : '')
                . 'context only (a strong tier, or the 3M window alone), not an exit; the alert does not email.';
        }

        $text .= ' A weak' . ($tier !== null ? ' ' . $tier : '') . ' tier near a high: a chance to exit;'
            . ' the alert emails.';
        $action = $reading['action'] ?? null;
        if ($action !== null) {
            $text .= ' ' . $action . ': '
                . (self::ACTION_ADVICE[$action] ?? 'as the tier signal reads it (its chip)') . '.';
        }

        return $text;
    }
}

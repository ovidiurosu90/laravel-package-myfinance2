<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

final class QuadrantClassifier
{
    const STEADY_GROWERS   = 'STEADY_GROWERS';
    const VOLATILE_WINNERS = 'VOLATILE_WINNERS';
    const DEAD_WEIGHT      = 'DEAD_WEIGHT';
    const DANGER_ZONE      = 'DANGER_ZONE';

    public const  RETURN_THRESHOLD   = 10.0;
    public const  DRAWDOWN_THRESHOLD = 1.25;

    private const ACTIONS = [
        self::STEADY_GROWERS   => 'ACCUMULATE',
        self::VOLATILE_WINNERS => 'HOLD',
        self::DEAD_WEIGHT      => 'REDUCE',
        self::DANGER_ZONE      => 'EXIT',
    ];

    private const UNOWNED_ACTIONS = [
        self::STEADY_GROWERS   => 'ACCUMULATE',
        self::VOLATILE_WINNERS => 'WATCH',
        self::DEAD_WEIGHT      => 'SKIP',
        self::DANGER_ZONE      => 'AVOID',
    ];

    public static function classify(?float $annualizedPct, ?float $relativeDrawdown): ?string
    {
        if ($annualizedPct === null || $relativeDrawdown === null) {
            return null;
        }

        $highReturn  = $annualizedPct >= self::RETURN_THRESHOLD;
        $lowDrawdown = $relativeDrawdown <= self::DRAWDOWN_THRESHOLD;

        if ($highReturn  && $lowDrawdown)  return self::STEADY_GROWERS;
        if ($highReturn  && !$lowDrawdown) return self::VOLATILE_WINNERS;
        if (!$highReturn && $lowDrawdown)  return self::DEAD_WEIGHT;
        return self::DANGER_ZONE;
    }

    public static function getAction(?string $quadrant, bool $isOwned = true): ?string
    {
        if ($quadrant === null) {
            return null;
        }
        $map = $isOwned ? self::ACTIONS : self::UNOWNED_ACTIONS;
        return $map[$quadrant] ?? null;
    }

    public static function label(?string $quadrant): ?string
    {
        $labels = [
            self::STEADY_GROWERS   => 'Steady grower',
            self::VOLATILE_WINNERS => 'Volatile winner',
            self::DEAD_WEIGHT      => 'Dead weight',
            self::DANGER_ZONE      => 'Danger zone',
        ];
        return $quadrant !== null ? ($labels[$quadrant] ?? $quadrant) : null;
    }
}

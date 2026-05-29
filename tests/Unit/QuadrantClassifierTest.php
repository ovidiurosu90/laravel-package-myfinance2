<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use ovidiuro\myfinance2\App\Services\QuadrantClassifier;
use PHPUnit\Framework\TestCase;

/**
 * Locks down the action quadrant a symbol lands in. The quadrant drives the
 * ACCUMULATE / HOLD / REDUCE / EXIT advice shown next to every position, so the
 * two threshold edges (return >= 10%/yr, relative drawdown <= 1.25x the index)
 * and their boundary behaviour must not drift. Pure static logic, no database.
 */
class QuadrantClassifierTest extends TestCase
{
    public function testHighReturnLowDrawdownIsSteadyGrower(): void
    {
        $this->assertSame(
            QuadrantClassifier::STEADY_GROWERS,
            QuadrantClassifier::classify(20.0, 1.0)
        );
    }

    public function testHighReturnHighDrawdownIsVolatileWinner(): void
    {
        $this->assertSame(
            QuadrantClassifier::VOLATILE_WINNERS,
            QuadrantClassifier::classify(20.0, 2.0)
        );
    }

    public function testLowReturnLowDrawdownIsDeadWeight(): void
    {
        $this->assertSame(
            QuadrantClassifier::DEAD_WEIGHT,
            QuadrantClassifier::classify(3.0, 1.0)
        );
    }

    public function testLowReturnHighDrawdownIsDangerZone(): void
    {
        $this->assertSame(
            QuadrantClassifier::DANGER_ZONE,
            QuadrantClassifier::classify(3.0, 2.0)
        );
    }

    public function testReturnThresholdIsInclusiveAtTenPercent(): void
    {
        // Exactly 10%/yr counts as high return (>=), so with low drawdown it is a steady grower.
        $this->assertSame(
            QuadrantClassifier::STEADY_GROWERS,
            QuadrantClassifier::classify(QuadrantClassifier::RETURN_THRESHOLD, 1.0)
        );
        // A hair under flips to dead weight.
        $this->assertSame(
            QuadrantClassifier::DEAD_WEIGHT,
            QuadrantClassifier::classify(QuadrantClassifier::RETURN_THRESHOLD - 0.01, 1.0)
        );
    }

    public function testDrawdownThresholdIsInclusiveAtBoundary(): void
    {
        // Exactly 1.25x the index drawdown still counts as low drawdown (<=).
        $this->assertSame(
            QuadrantClassifier::STEADY_GROWERS,
            QuadrantClassifier::classify(20.0, QuadrantClassifier::DRAWDOWN_THRESHOLD)
        );
        // A hair over flips a winner to volatile.
        $this->assertSame(
            QuadrantClassifier::VOLATILE_WINNERS,
            QuadrantClassifier::classify(20.0, QuadrantClassifier::DRAWDOWN_THRESHOLD + 0.01)
        );
    }

    public function testNullInputsAreUnclassified(): void
    {
        $this->assertNull(QuadrantClassifier::classify(null, 1.0));
        $this->assertNull(QuadrantClassifier::classify(20.0, null));
        $this->assertNull(QuadrantClassifier::classify(null, null));
    }

    public function testNegativeReturnIsLowReturn(): void
    {
        $this->assertSame(
            QuadrantClassifier::DEAD_WEIGHT,
            QuadrantClassifier::classify(-5.0, 1.0)
        );
        $this->assertSame(
            QuadrantClassifier::DANGER_ZONE,
            QuadrantClassifier::classify(-5.0, 3.0)
        );
    }

    public function testOwnedActionsMapToHoldingAdvice(): void
    {
        $this->assertSame('ACCUMULATE', QuadrantClassifier::getAction(QuadrantClassifier::STEADY_GROWERS));
        $this->assertSame('HOLD', QuadrantClassifier::getAction(QuadrantClassifier::VOLATILE_WINNERS));
        $this->assertSame('REDUCE', QuadrantClassifier::getAction(QuadrantClassifier::DEAD_WEIGHT));
        $this->assertSame('EXIT', QuadrantClassifier::getAction(QuadrantClassifier::DANGER_ZONE));
    }

    public function testUnownedActionsMapToWatchlistAdvice(): void
    {
        // Watchlist (not owned) advice never tells you to sell something you do not hold.
        $this->assertSame('ACCUMULATE', QuadrantClassifier::getAction(QuadrantClassifier::STEADY_GROWERS, false));
        $this->assertSame('WATCH', QuadrantClassifier::getAction(QuadrantClassifier::VOLATILE_WINNERS, false));
        $this->assertSame('SKIP', QuadrantClassifier::getAction(QuadrantClassifier::DEAD_WEIGHT, false));
        $this->assertSame('AVOID', QuadrantClassifier::getAction(QuadrantClassifier::DANGER_ZONE, false));
    }

    public function testActionAndLabelAreNullForNullQuadrant(): void
    {
        $this->assertNull(QuadrantClassifier::getAction(null));
        $this->assertNull(QuadrantClassifier::getAction(null, false));
        $this->assertNull(QuadrantClassifier::label(null));
    }

    public function testLabelsAreHumanReadable(): void
    {
        $this->assertSame('Steady grower', QuadrantClassifier::label(QuadrantClassifier::STEADY_GROWERS));
        $this->assertSame('Volatile winner', QuadrantClassifier::label(QuadrantClassifier::VOLATILE_WINNERS));
        $this->assertSame('Dead weight', QuadrantClassifier::label(QuadrantClassifier::DEAD_WEIGHT));
        $this->assertSame('Danger zone', QuadrantClassifier::label(QuadrantClassifier::DANGER_ZONE));
    }
}

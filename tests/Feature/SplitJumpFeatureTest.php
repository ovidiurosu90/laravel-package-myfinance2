<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use ovidiuro\myfinance2\Tests\TestCase;

use ovidiuro\myfinance2\App\Models\PriceAlert;
use ovidiuro\myfinance2\App\Models\StatHistorical;
use ovidiuro\myfinance2\App\Services\AlertService;
use ovidiuro\myfinance2\App\Services\SplitDetectionService;

/**
 * A price alert's target far from the price is only a split anomaly when the price made a split-sized jump since the
 * alert was created (SplitDetectionService::hasSplitJumpSince()). Regression: a stock that fell by about 30 % on an
 * earnings day (no split) left an old PRICE_ABOVE target 3x away, and a "split" maintenance email went out every day.
 * Writes only stats_historical rows of the synthetic TST.SPLIT symbol (query builder: the table has no user_id),
 * rolled back.
 */
class SplitJumpFeatureTest extends TestCase
{
    private const SYMBOL = 'TST.SPLIT';

    /**
     * @param array<string, float> $closes date => close
     */
    private function _closes(array $closes): void
    {
        $model = new StatHistorical();
        $rows  = [];
        foreach ($closes as $date => $close) {
            $rows[] = [
                'date'              => $date,
                'symbol'            => self::SYMBOL,
                'unit_price'        => $close,
                'currency_iso_code' => 'USD',
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
        }
        DB::connection($model->getConnectionName())->table($model->getTable())->insert($rows);
    }

    /** A steep fall over weeks, the largest day -29 %, no split. */
    private function _steepFall(): void
    {
        $this->_closes([
            '2026-10-27' => 136.46,
            '2026-10-28' => 134.70,
            '2026-10-29' => 129.60,
            '2026-10-30' => 91.73,
            '2026-11-02' => 90.97,
        ]);
    }

    public function test_a_steep_fall_is_not_a_split_jump(): void
    {
        $this->_steepFall();

        $this->assertFalse(SplitDetectionService::hasSplitJumpSince(self::SYMBOL, Carbon::parse('2026-10-01'), 90.5));
    }

    public function test_a_three_for_one_drop_between_closes_is_a_split_jump(): void
    {
        $this->_closes(['2026-10-28' => 300.0, '2026-10-29' => 301.0, '2026-10-30' => 100.2]);

        $this->assertTrue(SplitDetectionService::hasSplitJumpSince(self::SYMBOL, Carbon::parse('2026-10-01'), 101.0));
    }

    public function test_a_split_taking_effect_today_is_a_jump_to_the_current_price(): void
    {
        $this->_closes(['2026-10-28' => 300.0, '2026-10-29' => 301.0]);

        $this->assertTrue(SplitDetectionService::hasSplitJumpSince(self::SYMBOL, Carbon::parse('2026-10-01'), 100.0));
    }

    public function test_a_jump_before_the_alert_was_created_does_not_count(): void
    {
        $this->_closes(['2026-09-01' => 300.0, '2026-09-02' => 100.0, '2026-10-29' => 101.0]);

        $this->assertFalse(SplitDetectionService::hasSplitJumpSince(self::SYMBOL, Carbon::parse('2026-10-01'), 100.5));
    }

    public function test_an_alert_far_above_a_fallen_price_is_not_a_split_anomaly(): void
    {
        $this->_steepFall();
        $alert = $this->_alert(274.4615);

        $this->assertTrue(SplitDetectionService::isAlertTargetStale('PRICE_ABOVE', 274.4615, 90.97));
        $this->assertFalse($this->_isPotentialSplitAnomaly($alert, 90.97));
    }

    public function test_an_alert_far_above_the_price_after_a_split_jump_is_a_split_anomaly(): void
    {
        $this->_closes(['2026-10-28' => 300.0, '2026-10-29' => 301.0, '2026-10-30' => 100.2]);
        $alert = $this->_alert(310.0);

        $this->assertTrue($this->_isPotentialSplitAnomaly($alert, 100.2));
    }

    /**
     * An unsaved PRICE_ABOVE alert on TST.SPLIT created 2026-04-04.
     */
    private function _alert(float $target): PriceAlert
    {
        $alert = new PriceAlert();
        $alert->symbol     = self::SYMBOL;
        $alert->alert_type = 'PRICE_ABOVE';
        $alert->setRawAttributes(array_merge($alert->getAttributes(), [
            'target_price' => $target,
            'created_at'   => '2026-04-04 11:51:05',
        ]), true);

        return $alert;
    }

    private function _isPotentialSplitAnomaly(PriceAlert $alert, float $price): bool
    {
        $method = new \ReflectionMethod(AlertService::class, '_isPotentialSplitAnomaly');
        $method->setAccessible(true);

        return (bool) $method->invoke(app(AlertService::class), $alert, $price);
    }
}

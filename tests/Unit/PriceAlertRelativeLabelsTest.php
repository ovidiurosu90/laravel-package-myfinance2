<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ovidiuro\myfinance2\App\Models\PriceAlert;

/**
 * Unit tests for the relative-target label helpers on PriceAlert. Bare (unsaved) models, no DB.
 */
class PriceAlertRelativeLabelsTest extends TestCase
{
    private function _makeRelative(string $type, string $window, string $offset): PriceAlert
    {
        $alert = new PriceAlert();
        $alert->setRawAttributes([
            'target_mode'      => 'RELATIVE',
            'reference_type'   => $type,
            'reference_window' => $window,
            'offset_pct'       => $offset,
        ], true);

        return $alert;
    }

    public function test_long_labels(): void
    {
        $this->assertSame('5% below 52W high', PriceAlert::buildRelativeLabel('HIGH', '1y', 5.0));
        $this->assertSame('20% below 2Y high', PriceAlert::buildRelativeLabel('HIGH', '2y', 20.0));
        $this->assertSame('10% above 6M low', PriceAlert::buildRelativeLabel('LOW', '6m', 10.0));
        $this->assertSame('at 52W high', PriceAlert::buildRelativeLabel('HIGH', '1y', 0.0));
        $this->assertSame('at 3M low', PriceAlert::buildRelativeLabel('LOW', '3m', 0.0));
        $this->assertSame('2.5% below 3M high', PriceAlert::buildRelativeLabel('HIGH', '3m', 2.5));
    }

    public function test_short_labels(): void
    {
        $this->assertSame('52W H -5%', PriceAlert::buildShortRelativeLabel('HIGH', '1y', 5.0));
        $this->assertSame('3M L +10%', PriceAlert::buildShortRelativeLabel('LOW', '3m', 10.0));
        $this->assertSame('52W H', PriceAlert::buildShortRelativeLabel('HIGH', '1y', 0.0));
    }

    public function test_model_helpers_read_the_reference_columns(): void
    {
        $alert = $this->_makeRelative('HIGH', '1y', '5.000');

        $this->assertTrue($alert->isRelative());
        $this->assertSame('5% below 52W high', $alert->getRelativeTargetLabel());
        $this->assertSame('52W H -5%', $alert->getShortRelativeLabel());
    }

    public function test_fixed_alert_has_no_relative_labels(): void
    {
        $alert = new PriceAlert();
        $alert->setRawAttributes(['target_mode' => 'FIXED'], true);

        $this->assertFalse($alert->isRelative());
        $this->assertNull($alert->getRelativeTargetLabel());
        $this->assertNull($alert->getShortRelativeLabel());
        $this->assertNull($alert->getTargetLabelSnapshot());
        $this->assertFalse($alert->isReferenceStale());
    }

    public function test_target_label_snapshot_includes_the_reference(): void
    {
        $alert = $this->_makeRelative('HIGH', '1y', '5.000');
        $alert->setRawAttributes(array_merge($alert->getAttributes(), [
            'reference_price' => '123.450000',
        ]), true);

        $this->assertSame('5% below 52W high, ref 123.45', $alert->getTargetLabelSnapshot());
    }

    public function test_paused_or_expired_relative_alert_is_never_stale(): void
    {
        $paused = $this->_makeRelative('HIGH', '1y', '5.000');
        $paused->status = 'PAUSED';

        // Raw Carbon values, so reading the cast needs no DB connection for the date format
        $expired = $this->_makeRelative('HIGH', '1y', '5.000');
        $expired->setRawAttributes(array_merge($expired->getAttributes(), [
            'status'     => 'ACTIVE',
            'expires_at' => Carbon::parse('2020-01-01'),
        ]), true);

        $this->assertFalse($paused->isReferenceStale());
        $this->assertFalse($expired->isReferenceStale());
    }

    public function test_badge_tooltip_lines_carry_the_price_and_escape_the_label(): void
    {
        $alert = $this->_makeRelative('HIGH', '1y', '5.000');
        $alert->status = 'PAUSED';

        $this->assertSame(
            ['Target: 114.00 &euro; (5% below 52W high)'],
            $alert->getRelativeBadgeTooltipLines('114.00 &euro;')
        );

        $fixed = new PriceAlert();
        $fixed->setRawAttributes(['target_mode' => 'FIXED'], true);
        $this->assertSame([], $fixed->getRelativeBadgeTooltipLines('150.00 $'));
    }

    public function test_reference_options_cover_every_type_and_window(): void
    {
        $options = PriceAlert::referenceOptions();

        $this->assertCount(8, $options);
        $this->assertSame('52W high', $options['HIGH:1y']);
        $this->assertSame('3M low', $options['LOW:3m']);
        $this->assertSame('HIGH:3m', array_key_first($options));
    }
}

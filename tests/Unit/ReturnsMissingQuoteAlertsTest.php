<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

use ovidiuro\myfinance2\App\Services\Returns\ReturnsMissingQuoteAlerts;

/**
 * Pure tests for the returns alert that surfaces held positions valued at 0 because no price was found
 * (e.g. a delisted symbol whose history the finance API dropped). No DB, no HTTP.
 */
class ReturnsMissingQuoteAlertsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container();
        $container->instance('config', new ConfigRepository([
            'trades' => ['delisted_symbols' => ['WBD']],
        ]));
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function testNoAlertWhenEveryPositionHasAPrice(): void
    {
        $returnsData = [
            4 => $this->_account('Broker B', [], []),
            'totalReturnEUR' => 0,
        ];

        $this->assertSame([], (new ReturnsMissingQuoteAlerts())->check($returnsData, 2025));
    }

    public function testOneAlertPerValuationDateSortedBySymbolThenAccount(): void
    {
        $returnsData = [
            5 => $this->_account('Broker A', [], [$this->_missing('WBD', 20, '2025-12-31')]),
            4 => $this->_account('Broker B', [$this->_missing('XYZ', 3, '2025-01-01')], [
                $this->_missing('WBD', 30, '2025-12-31'),
                $this->_missing('ABC', 10, '2025-12-31'),
            ]),
            // Metadata and virtual entries are ignored
            'totalReturnEUR' => 0,
            'virtual_transfer-adjustments' => ['dec31MissingQuotes' => [$this->_missing('IGN', 1, '2025-12-31')]],
        ];

        $alerts = (new ReturnsMissingQuoteAlerts())->check($returnsData, 2025);

        $this->assertCount(2, $alerts);
        $this->assertSame('missing_quote_jan1', $alerts[0]['type']);
        $this->assertStringContainsString('Jan 1, 2025', $alerts[0]['message']);
        $this->assertStringContainsString('config date: 2024-12-31', $alerts[0]['message']);
        $this->assertSame(['XYZ'], array_column($alerts[0]['positions'], 'symbol'));

        $this->assertSame('missing_quote_dec31', $alerts[1]['type']);
        $this->assertStringContainsString('Dec 31, 2025', $alerts[1]['message']);
        $positions = $alerts[1]['positions'];
        $this->assertSame(['ABC', 'WBD', 'WBD'], array_column($positions, 'symbol'));
        $this->assertSame(['Broker B', 'Broker A', 'Broker B'], array_column($positions, 'account_name'));
        $this->assertSame([4, 5, 4], array_column($positions, 'account_id'));
        $this->assertSame([false, true, true], array_column($positions, 'delisted'));
        $this->assertSame(20.0, $positions[1]['quantity']);
    }

    private function _account(string $name, array $jan1Missing, array $dec31Missing): array
    {
        return [
            'account' => (object) ['name' => $name],
            'jan1MissingQuotes' => $jan1Missing,
            'dec31MissingQuotes' => $dec31Missing,
        ];
    }

    private function _missing(string $symbol, float $quantity, string $date): array
    {
        return ['symbol' => $symbol, 'quantity' => $quantity, 'date' => $date];
    }
}

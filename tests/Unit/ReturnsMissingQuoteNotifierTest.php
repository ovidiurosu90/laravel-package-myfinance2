<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use ovidiuro\myfinance2\App\Services\Returns\ReturnsMissingQuoteNotifier;

/**
 * Pure tests for the returns refresh cron's missing-price email: one email per owner covering every
 * recomputed year, and no repeat for the same gaps on the same day (the current year is recomputed
 * hourly). The email itself is captured by a subclass. No DB, no HTTP, no mailer.
 */
class ReturnsMissingQuoteNotifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container();
        $container->instance('config', new ConfigRepository(['trades' => ['delisted_symbols' => ['WBD']]]));
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        Cache::swap(new CacheRepository(new ArrayStore()));
        Log::swap(new NullLogger());
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function testOneEmailPerOwnerCoveringEveryYear(): void
    {
        $notifier = $this->_notifier();
        $notifier->collect(2024, $this->_returnsData('2024-12-31', 1));
        $notifier->collect(2025, $this->_returnsData('2025-12-31', 1));
        $notifier->collect(2023, $this->_returnsData(null, 1)); // nothing missing: not emailed
        $notifier->send();

        $this->assertCount(1, $notifier->sent);
        $this->assertSame(1, $notifier->sent[0]['user_id']);
        $this->assertSame([2024, 2025], array_keys($notifier->sent[0]['years']));
        $this->assertSame('WBD', $notifier->sent[0]['years'][2025][0]['symbol']);
    }

    public function testSameGapsAreNotEmailedTwiceTheSameDay(): void
    {
        $first = $this->_notifier();
        $first->collect(2026, $this->_returnsData('2026-10-09', 1));
        $first->send();
        $this->assertCount(1, $first->sent);

        // Next hourly run: same gaps, same day
        $second = $this->_notifier();
        $second->collect(2026, $this->_returnsData('2026-10-09', 1));
        $second->send();
        $this->assertCount(0, $second->sent);

        // The gaps change (a second symbol): emailed again, only for that year
        $third = $this->_notifier();
        $third->collect(2026, $this->_returnsData('2026-10-09', 1, ['WBD', 'XYZ']));
        $third->send();
        $this->assertCount(1, $third->sent);
    }

    public function testFailedEmailIsRetriedOnTheNextRun(): void
    {
        $failing = $this->_notifier(false);
        $failing->collect(2025, $this->_returnsData('2025-12-31', 1));
        $failing->send();

        $retry = $this->_notifier();
        $retry->collect(2025, $this->_returnsData('2025-12-31', 1));
        $retry->send();
        $this->assertCount(1, $retry->sent);
    }

    private function _notifier(bool $succeeds = true): ReturnsMissingQuoteNotifier
    {
        return new class($succeeds) extends ReturnsMissingQuoteNotifier
        {
            public array $sent = [];

            public function __construct(private bool $_succeeds)
            {
            }

            protected function _email(int $userId, array $positionsByYear): bool
            {
                if ($this->_succeeds) {
                    $this->sent[] = ['user_id' => $userId, 'years' => $positionsByYear];
                }
                return $this->_succeeds;
            }
        };
    }

    private function _returnsData(?string $date, int $userId, array $symbols = ['WBD']): array
    {
        $missing = $date === null ? [] : array_map(
            fn(string $symbol) => ['symbol' => $symbol, 'quantity' => 10.0, 'date' => $date],
            $symbols
        );

        return [
            4 => [
                'account' => (object) ['name' => 'Broker A', 'user_id' => $userId],
                'jan1MissingQuotes' => [],
                'dec31MissingQuotes' => $missing,
            ],
            'totalReturnEUR' => 0,
        ];
    }
}

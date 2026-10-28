<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Support;

use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;
use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\TestRunner\ExecutionStartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * The feature tests run on the host's local copy of the production databases, so each run must leave them as it
 * found them: every table is read before the first test and after the last (TableSnapshot), and any row the run left
 * behind fails it with a warning naming the tables (LeftoverRowsCheck). Tables in the 'ignore' parameter
 * ("connection.table", comma-separated) are left out. Catches rows added anywhere and rows removed from the tables
 * without an id; not changes to rows.
 */
final class LeftoverRowsExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $ignored = $parameters->has('ignore') ? $parameters->get('ignore') : '';
        $check   = new LeftoverRowsCheck(new TableSnapshot(array_values(array_filter(array_map('trim',
            explode(',', $ignored))))));

        $facade->registerSubscriber(new class ($check) implements ExecutionStartedSubscriber
        {
            private const FEATURE_TESTS = 'ovidiuro\\myfinance2\\Tests\\Feature\\';

            public function __construct(private readonly LeftoverRowsCheck $check)
            {
            }

            public function notify(ExecutionStarted $event): void
            {
                // A unit-only run never touches a database (and may run where there is none)
                foreach ($event->testSuite()->tests() as $test) {
                    if ($test->isTestMethod() && str_starts_with($test->className(), self::FEATURE_TESTS)) {
                        $this->check->start();
                        return;
                    }
                }
            }
        });

        $facade->registerSubscriber(new class ($check) implements ExecutionFinishedSubscriber
        {
            public function __construct(private readonly LeftoverRowsCheck $check)
            {
            }

            public function notify(ExecutionFinished $event): void
            {
                $warning = $this->check->finish();
                if ($warning !== null) {
                    EventFacade::emitter()->testRunnerTriggeredPhpunitWarning($warning);
                }
            }
        });
    }
}

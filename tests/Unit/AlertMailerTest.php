<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use ovidiuro\myfinance2\App\Services\AlertMailer;

/**
 * Unit tests for AlertMailer::isTransient(): which mail failures are retried and which fail at once.
 */
class AlertMailerTest extends TestCase
{
    public function test_smtp_4xx_reply_is_transient(): void
    {
        $e = new UnexpectedResponseException(
            'Expected response code "220" but got code "421", with message "421 4.4.5 Server busy".',
            421
        );
        $this->assertTrue(AlertMailer::isTransient($e));
    }

    public function test_smtp_5xx_reply_is_permanent(): void
    {
        $e = new UnexpectedResponseException('Expected response code "250" but got code "550".', 550);
        $this->assertFalse(AlertMailer::isTransient($e));
    }

    public function test_connection_level_transport_error_is_transient(): void
    {
        $e = new TransportException('Connection to "smtp.example.test:587" has been closed unexpectedly.');
        $this->assertTrue(AlertMailer::isTransient($e));
    }

    public function test_non_transport_error_is_permanent(): void
    {
        $this->assertFalse(AlertMailer::isTransient(new \RuntimeException('View [x] not found.')));
    }
}

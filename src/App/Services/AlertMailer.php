<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;

/**
 * Sends alert emails, retrying transient SMTP failures in-process.
 *
 * A 4xx SMTP reply (e.g. "421 Server busy, try again later") or a dropped connection is the server
 * asking us to come back shortly, so it is retried a few times with a short backoff and logged only
 * as a warning. A permanent 5xx reply, or anything that is not a transport error, fails at once.
 * When every attempt fails the last exception is rethrown, so callers keep their own error handling
 * (FAILED audit row + error log), which then marks a genuinely undelivered alert.
 */
final class AlertMailer
{
    // Seconds to wait before each retry; the number of entries is the number of retries.
    private const RETRY_DELAYS = [5, 15];

    /**
     * @param string|array $to
     * @param string       $context  Log prefix identifying the caller (e.g. "PortfolioPeakAlertService").
     *
     * @throws \Throwable  The last failure, when it is permanent or every retry is exhausted.
     */
    public static function send(string|array $to, Mailable $mailable, string $context): void
    {
        $attempts = count(self::RETRY_DELAYS) + 1;

        for ($attempt = 1; ; $attempt++)
        {
            try {
                Mail::to($to)->send($mailable);
                return;
            } catch (\Throwable $e) {
                if ($attempt >= $attempts || !self::isTransient($e))
                {
                    throw $e;
                }
                $delay = self::RETRY_DELAYS[$attempt - 1];
                Log::warning("{$context}: transient email failure (attempt {$attempt}/{$attempts}),"
                    . " retrying in {$delay}s: " . $e->getMessage());
                sleep($delay);
                self::_resetTransport();
            }
        }
    }

    /**
     * Drop the SMTP connection so the retry starts a fresh session. A failure mid-conversation (e.g.
     * a 451 after RCPT TO) leaves the transport marked as started, and reusing that session would
     * send MAIL FROM inside an unfinished transaction. stop() sends QUIT and swallows its own errors.
     */
    private static function _resetTransport(): void
    {
        $mailer = Mail::mailer();
        if (method_exists($mailer, 'getSymfonyTransport'))
        {
            $transport = $mailer->getSymfonyTransport();
            if (method_exists($transport, 'stop'))
            {
                $transport->stop();
            }
        }
    }

    /**
     * Transient = an SMTP 4xx reply, or a transport error with no SMTP code at all (connection
     * refused, timed out or closed unexpectedly). SMTP 5xx replies are permanent.
     */
    public static function isTransient(\Throwable $e): bool
    {
        if ($e instanceof UnexpectedResponseException)
        {
            $code = (int) $e->getCode();
            return $code === 0 || ($code >= 400 && $code < 500);
        }

        return $e instanceof TransportExceptionInterface;
    }
}

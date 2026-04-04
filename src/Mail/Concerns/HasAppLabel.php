<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Mail\Concerns;

trait HasAppLabel
{
    /**
     * Returns the email subject prefix, e.g. "[MyFinance2]" on production
     * or "[MyFinance2-LOCAL]" on local, "[MyFinance2-STAGING]" on staging, etc.
     */
    private function _appLabel(): string
    {
        $env = app()->environment();

        if ($env === 'production') {
            return '[MyFinance2]';
        }

        return '[MyFinance2-' . strtoupper($env) . ']';
    }
}

<?php

namespace ovidiuro\myfinance2;

use Illuminate\Support\Facades\Facade;

class MyFinance2Facade extends Facade
{
    /**
     * Gets the facade accessor.
     *
     * @return string The facade accessor.
     */
    protected static function getFacadeAccessor()
    {
        return 'myfinance2';
    }
}


<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Support;

use Illuminate\Routing\Route;

/**
 * Calls a route without knowing its parameters: optional ones dropped, a symbol as TST.NONE, any other id as 0 (a
 * record nobody has, so the request stops at authorization or "not found", never at someone's data).
 */
trait FillsRouteParameters
{
    protected static function filledUri(Route $route): string
    {
        $uri = preg_replace('#/\{\w+\?\}#', '', $route->uri());

        return '/' . ltrim(preg_replace_callback('#\{(\w+)\}#',
            fn (array $match) => $match[1] === 'symbol' ? 'TST.NONE' : '0', $uri), '/');
    }

    protected static function firstMethod(Route $route): string
    {
        return current(array_diff($route->methods(), ['HEAD']));
    }
}

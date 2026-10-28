<?php

declare(strict_types=1);

namespace ovidiuro\myfinance2\Tests\Support;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * The feature tests run on the host app's databases (its local copy of production; there is no test database), so
 * the host's .env gives the connection settings: MYFINANCE2_TEST_ENV_FILE, else the .env of the app this package is
 * installed in (<app>/vendor/ovidiuro/myfinance2). Only its DB_* and MYFINANCE2_* keys are read, and a key already
 * set in the environment wins.
 */
final class HostEnvironment
{
    private static bool $_loaded = false;

    public static function load(): void
    {
        if (self::$_loaded) {
            return;
        }

        $path = getenv('MYFINANCE2_TEST_ENV_FILE') ?: dirname(__DIR__, 5) . '/.env';
        if (!is_readable($path)) {
            throw new RuntimeException("The feature tests need the host app's .env (database settings): {$path} "
                . 'is not readable; set MYFINANCE2_TEST_ENV_FILE.');
        }

        foreach (Dotenv::parse((string) file_get_contents($path)) as $key => $value) {
            if (!preg_match('/^(DB|MYFINANCE2)_/', $key) || getenv($key) !== false || $value === null) {
                continue;
            }
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
        self::$_loaded = true;
    }

    /**
     * The settings of the host's two databases: the app's own (users, as DB_CONNECTION) and MyFinance2's.
     *
     * @return array<string, array<string, mixed>> connection name => Laravel connection settings
     */
    public static function connections(): array
    {
        self::load();
        $package = require dirname(__DIR__, 2) . '/src/config/myfinance2.php';

        return [
            (string) (getenv('DB_CONNECTION') ?: 'mysql') => [
                'driver'      => 'mysql',
                'host'        => getenv('DB_HOST') ?: '127.0.0.1',
                'port'        => getenv('DB_PORT') ?: '3306',
                'database'    => (string) getenv('DB_DATABASE'),
                'username'    => (string) getenv('DB_USERNAME'),
                'password'    => (string) getenv('DB_PASSWORD'),
                'unix_socket' => (string) getenv('DB_SOCKET'),
                'charset'     => 'utf8mb4',
                'collation'   => 'utf8mb4_unicode_ci',
                'prefix'      => '',
            ],
            $package['db_connection'] => $package['connections']['myfinance2_mysql'],
        ];
    }
}

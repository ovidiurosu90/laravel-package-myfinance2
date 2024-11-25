<?php

return [
    'defaultMigrations' => [
        'enabled' => env('MYFINANCE2_MIGRATION_DEFAULT_ENABLED', true),
    ],

    'db_connection' => env('MYFINANCE2_DB_CONNECTION', 'myfinance2_mysql'),

    'connections' => [
        'myfinance2_mysql' => [
            'driver'         => 'mysql',
            'url'            => env('MYFINANCE2_DATABASE_URL'),
            'host'           => env('MYFINANCE2_DB_HOST', '127.0.0.1'),
            'port'           => env('MYFINANCE2_DB_PORT', '3306'),
            'database'       => env('MYFINANCE2_DB_DATABASE', 'myfinance2'),
            'username'       => env('MYFINANCE2_DB_USERNAME', 'myfinance2_user'),
            'password'       => env('MYFINANCE2_DB_PASSWORD', ''),
            'unix_socket'    => env('MYFINANCE2_DB_SOCKET', ''),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => null,
            'options'        => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
    ],
];


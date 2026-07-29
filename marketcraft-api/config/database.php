<?php

return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [

        'mysql' => [
            'driver'         => 'mysql',
            'url'            => env('DB_URL'),
            'host'           => env('DB_HOST', '127.0.0.1'),
            'port'           => env('DB_PORT', '3306'),
            'database'       => env('DB_DATABASE', 'marketcraft_4eme_dev'),
            'username'       => env('DB_USERNAME', 'root'),
            'password'       => env('DB_PASSWORD', ''),
            'unix_socket'    => env('DB_SOCKET', ''),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => 'InnoDB',
            'options'        => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                // Prepared statements natifs : les entiers et decimaux
                // reviennent typés, comme dans l'ancien back-end.
                PDO::ATTR_EMULATE_PREPARES => false,
            ]) : [],
        ],

        // Utilisee par la suite de tests (php artisan test).
        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => env('DB_DATABASE_TEST', ':memory:'),
            'prefix'   => '',
            'foreign_key_constraints' => true,
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
];

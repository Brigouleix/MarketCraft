<?php

use Monolog\Handler\StreamHandler;

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => 'null',
        'trace'   => false,
    ],

    'channels' => [
        'stack' => [
            'driver'            => 'stack',
            'channels'          => ['daily'],
            'ignore_exceptions' => false,
        ],

        'daily' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/laravel.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'days'   => 14,
        ],

        // Journal dedie au module IA : chaque echec y est trace avec son
        // motif. Aucun `return null` muet.
        'ia' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/ia.log'),
            'level'  => 'debug',
            'days'   => 30,
        ],

        'stderr' => [
            'driver'  => 'monolog',
            'handler' => StreamHandler::class,
            'with'    => ['stream' => 'php://stderr'],
        ],

        'null' => [
            'driver'  => 'monolog',
            'handler' => Monolog\Handler\NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];

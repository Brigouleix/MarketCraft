<?php

return [
    'name'     => env('APP_NAME', 'MarketCraft'),
    'env'      => env('APP_ENV', 'production'),
    'debug'    => (bool) env('APP_DEBUG', false),
    'url'      => env('APP_URL', 'http://localhost:8001'),
    'timezone' => env('APP_TIMEZONE', 'Europe/Paris'),
    'locale'   => 'fr',
    'fallback_locale' => 'fr',
    'faker_locale'    => 'fr_FR',
    'key'    => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',

    'maintenance' => [
        'driver' => 'file',
    ],
];

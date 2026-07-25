<?php

return [
    'defaults' => [
        'guard'     => 'api',
        'passwords' => 'users',
    ],

    // L'API est sans etat : l'utilisateur courant est resolu par le
    // middleware JWT, pas par un guard de session.
    'guards' => [
        'api' => [
            'driver'   => 'jwt',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];

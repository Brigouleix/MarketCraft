<?php

return [
    // bcrypt, cout 12 : identique a l'ancien back-end, donc les hachages
    // deja en base restent valides. SHA-1 et MD5 sont exclus par construction.
    'driver' => 'bcrypt',

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => true,
    ],

    'argon' => [
        'memory'  => 65536,
        'threads' => 1,
        'time'    => 4,
        'verify'  => true,
    ],

    'rehash_on_login' => true,
];

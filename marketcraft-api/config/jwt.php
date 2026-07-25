<?php

return [
    // Signature HS256. La cle vit uniquement dans .env, jamais dans le depot.
    'secret'      => env('JWT_SECRET'),
    'algo'        => 'HS256',
    'issuer'      => env('APP_URL', 'marketcraft'),

    // Duree de vie, en secondes.
    'ttl'         => (int) env('JWT_TTL', 86400),          // 24 h
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 604800), // 7 jours
];

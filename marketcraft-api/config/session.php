<?php

// L'API est sans etat et n'ouvre pas de session. Ce fichier n'existe que
// parce que certains composants du framework lisent config('session.*').
return [
    'driver'          => env('SESSION_DRIVER', 'file'),
    'lifetime'        => 120,
    'expire_on_close' => false,
    'encrypt'         => false,
    'files'           => storage_path('framework/sessions'),
    'connection'      => null,
    'table'           => 'sessions',
    'store'           => null,
    'lottery'         => [2, 100],
    'cookie'          => 'marketcraft_session',
    'path'            => '/',
    'domain'          => null,
    'secure'          => env('SESSION_SECURE_COOKIE', false),
    'http_only'       => true,
    'same_site'       => 'lax',
];

<?php

return [

    // --- CORS ---------------------------------------------------------------
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ALLOWED_ORIGINS', '*'))
    ))),

    // --- Verrouillage de compte (OWASP : Identification & Auth Failures) ----
    'auth' => [
        'max_attempts'      => (int) env('AUTH_MAX_ATTEMPTS', 5),
        'lockout_minutes'   => (int) env('AUTH_LOCKOUT_MINUTES', 15),
        'captcha_enabled'   => filter_var(env('AUTH_CAPTCHA_ENABLED', true), FILTER_VALIDATE_BOOL),
        'captcha_threshold' => (int) env('AUTH_CAPTCHA_THRESHOLD', 3),
        'captcha_ttl'       => 300, // secondes de validite d'un defi
    ],

    // --- Upload -------------------------------------------------------------
    'upload' => [
        'max_size'   => 5 * 1024 * 1024, // 5 Mo
        'max_files'  => 5,
        'mimes'      => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'directory'  => 'uploads',
    ],

    // --- Module IA : recommandation personnalisee (option C du CDC) ---------
    'ai' => [
        'key'     => env('AI_API_KEY'),
        'model'   => env('AI_MODEL', 'mistral-small-latest'),
        'url'     => env('AI_API_URL', 'https://api.mistral.ai/v1/chat/completions'),
        'timeout' => (int) env('AI_TIMEOUT', 12),
        // Sans User-Agent, Cloudflare renvoie 403 avant meme de lire la cle.
        'user_agent' => 'MarketCraft/1.0 (+https://marketcraft.local)',
    ],

    'pagination' => [
        'default_limit' => 20,
        'max_limit'     => 100,
    ],
];

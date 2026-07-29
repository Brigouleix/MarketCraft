<?php

return [
    'default' => env('FILESYSTEM_DISK', 'uploads'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
            'throw'  => false,
        ],

        // Les images sont servies directement par le serveur web depuis
        // public/uploads : l'URL stockee en base est absolue (APP_URL + chemin).
        'uploads' => [
            'driver'     => 'local',
            'root'       => public_path('uploads'),
            'url'        => env('APP_URL') . '/uploads',
            'visibility' => 'public',
            'throw'      => false,
        ],
    ],

    'links' => [],
];

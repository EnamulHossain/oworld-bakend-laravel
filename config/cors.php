<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique(array_filter([
        env('FRONTEND_APP_URL'),
        'http://localhost:3000',
        'http://127.0.0.1:3000',
<<<<<<< HEAD
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'https://oworldbd.com',
        'https://oworldbackend.ibrahimaaraf.com',
    ]))),
=======
         'https://oworldbd.com',
         'https://www.oworld.ibrahimaaraf.com',
         'https://oworldbackend.ibrahimaaraf.com',
    ],
>>>>>>> 3f368170e5c7bb757121570213c892cdd5d45a73
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];

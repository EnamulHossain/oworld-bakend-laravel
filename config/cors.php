<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_unique(array_filter([
        env('FRONTEND_APP_URL'),
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:9001',
        'http://127.0.0.1:9001',
        'https://oworldbd.com',
        'https://oworld.ibrahimaaraf.com',
        'https://owbacktest.ibrahimaaraf.com',
        'https://oworldbackend.ibrahimaaraf.com',
    ]))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];

<?php

$allowedOrigins = [env('FRONTEND_URL', 'http://localhost:3000')];

if (env('APP_ENV', 'production') === 'local') {
    $allowedOrigins = array_merge($allowedOrigins, [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ]);
}

$allowedOrigins = array_values(array_unique(array_filter($allowedOrigins)));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];

<?php

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

    'paths' => ['api/*', 'auth/*', 'sanctum/csrf-cookie', 'carts/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ["http://localhost:3000", "http://localhost:3000/", "http://192.168.100.9:3000", "http://178.128.115.99", "http://178.128.115.99/", "https://178.128.115.99", "https://178.128.115.99/", "https://167.172.69.43/", "https://167.172.69.43", "http://167.172.69.43/", "http://167.172.69.43"], 

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];

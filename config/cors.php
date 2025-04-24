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

    'allowed_origins' => [
        "http://localhost:3000", 
        "https://localhost:3000", 
        "http://192.168.100.9:3000", 
        "https://167.172.69.43",  
        "http://167.172.69.43", 
        "http://167.172.69.43:3000", 
        "https://167.172.69.43:3000",
        "http://167.172.69.43:3001",
        "https://167.172.69.43:3001",
        "https://cam-o2.com/",
        "http://cam-o2.com",
        "https://cam-o2.com",
        "https://o2-testing.vercel.app/",
        "https://o2-testing.vercel.app",
        "http://o2-testing.vercel.app/",
        "http://o2-testing.vercel.app/",
        "https://cam-o2-api.shop",
        "http://cam-o2-api.shop",
        "https://cam-o2-admin.shop"
    ], 
    
    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];

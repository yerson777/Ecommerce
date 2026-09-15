<?php

return [

    /* Rutas a las que aplica CORS. Las rutas api/* ya las incluye el paquete.
    'paths' => ['api/*', 'sanctum/csrf-cookie'], */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('CORS_TIENDA_ORIGIN', 'http://localhost:4200'),
        env('CORS_ADMIN_ORIGIN', 'http://localhost:4300'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
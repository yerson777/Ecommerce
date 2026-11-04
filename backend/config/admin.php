<?php

return [
    /*
    | Credenciales del primer usuario administrador. Se usan solo al ejecutar
    | el seeder; el password vacío genera uno temporal.
    */
    'email' => env('ADMIN_EMAIL', 'admin@everly.local'),
    'password' => env('ADMIN_PASSWORD'),
];

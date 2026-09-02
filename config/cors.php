<?php
// config/cors.php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*', 'run-link'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://tasswek.com',
        'https://www.tasswek.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];

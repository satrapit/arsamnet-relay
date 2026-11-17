<?php

return [
    'default' => env('PENNANT_STORE', 'database'),
    'stores' => [
        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CONNECTION', 'mysql'),
        ],
        'array' => ['driver' => 'array'],
    ],
];

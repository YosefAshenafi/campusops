<?php

return [
    // Default database connection
    'default' => 'mysql',

    // Database connections
    'connections' => [
        'mysql' => [
            'type'     => 'mysql',
            'hostname' => env('DB_HOST', '127.0.0.1'),
            'database' => env('DB_DATABASE', 'campusops'),
            'username' => env('DB_USERNAME', 'campusops'),
            'password' => env('DB_PASSWORD', ''),
            'hostport' => env('DB_PORT', '3306'),
            'charset'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'   => '',
            'debug'    => env('APP_DEBUG', false),

            // Connection pool
            'deploy'   => 0,
            'rw_separate' => false,

            // Fields cache
            'fields_cache' => false,

            // Trigger SQL log event
            'trigger_sql' => env('APP_DEBUG', false),
        ],
    ],
];

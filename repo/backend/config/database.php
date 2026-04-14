<?php

// Prefer real environment (e.g. Docker Compose) over backend/.env. ThinkPHP's
// env() merges .env into Env first, which otherwise overrides container DB_* and
// breaks when .env placeholders differ from MYSQL_PASSWORD used at DB init.
$dbEnv = static function (string $key, $default = null) {
    $v = getenv($key);
    if ($v !== false) {
        return $v;
    }

    return env($key, $default);
};

return [
    // Default database connection
    'default' => 'mysql',

    // Database connections
    'connections' => [
        'mysql' => [
            'type'     => 'mysql',
            'hostname' => $dbEnv('DB_HOST', '127.0.0.1'),
            'database' => $dbEnv('DB_DATABASE', 'campusops'),
            'username' => $dbEnv('DB_USERNAME', 'campusops'),
            'password' => $dbEnv('DB_PASSWORD', ''),
            'hostport' => $dbEnv('DB_PORT', '3306'),
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

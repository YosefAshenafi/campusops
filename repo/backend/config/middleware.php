<?php

// Global middleware configuration
return [
    // Global middleware
    'global' => [],

    // Alias middleware
    'alias' => [
        'auth' => app\middleware\AuthMiddleware::class,
        'rbac' => app\middleware\RbacMiddleware::class,
    ],
];

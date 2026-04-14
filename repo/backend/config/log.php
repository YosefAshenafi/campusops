<?php

return [
    // Default log channel
    'default' => 'file',

    // Log channels
    'channels' => [
        'file' => [
            // Driver type
            'type' => 'File',
            // Log file size limit
            'file_size' => 2097152,
            // Log save path
            'path' => '',
            // Single file logging
            'single' => false,
            // Independent log level
            'apart_level' => ['error', 'warning'],
            // Maximum log files
            'max_files' => 30,
            // Time format
            'time_format' => 'Y-m-d H:i:s',
            // Output format
            'format' => '[%s][%s] %s',
        ],
    ],
];

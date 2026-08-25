<?php

return [
    'lint' => [
        'env_outside_config' => true,
        'missing_example_keys' => true,
        'duplicate_env_keys' => true,
    ],

    'env_files' => [
        '.env.example',
        '.env.testing',
    ],

    'required' => [
        'production' => [
            // 'app.key',
            // 'database.connections.mysql.host',
        ],
    ],
];

<?php

return [
    'lint' => [
        'env_outside_config' => true,
        'missing_example_keys' => true,
        'duplicate_env_keys' => true,
    ],

    // Only application-owned config files belong here. Laravel/framework
    // optional overrides should not be forced into .env.example.
    'application_config' => [
        // 'config/payment.php',
        // 'config/order-import.php',
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

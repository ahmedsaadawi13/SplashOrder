<?php
// FILE: /config/app.php

/**
 * Application Configuration
 * Returns application settings
 */

return [
    'name' => $_ENV['APP_NAME'] ?? 'SplashOrder',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'timezone' => 'UTC',

    'pagination' => [
        'per_page' => (int)($_ENV['ITEMS_PER_PAGE'] ?? 20),
    ],

    'upload' => [
        'max_size' => (int)($_ENV['MAX_UPLOAD_SIZE'] ?? 5242880), // 5MB
        'allowed_images' => explode(',', $_ENV['ALLOWED_IMAGE_TYPES'] ?? 'jpg,jpeg,png,gif,webp'),
    ],

    'mail' => [
        'from' => $_ENV['MAIL_FROM'] ?? 'noreply@splashorder.com',
        'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'SplashOrder',
    ],

    'session' => [
        'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 7200),
    ],

    'security' => [
        'hash_algo' => $_ENV['HASH_ALGO'] ?? 'bcrypt',
        'hash_cost' => (int)($_ENV['HASH_COST'] ?? 10),
    ],
];

<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

return [
    'app_name' => 'UdharPay',
    'env' => EnvLoader::get('APP_ENV', 'development'),
    'debug' => EnvLoader::get('APP_DEBUG', 'false') === 'true',
    'frontend_origin' => EnvLoader::get('FRONTEND_ORIGIN', 'http://localhost:5173'),
    'session_ttl' => (int)EnvLoader::get('SESSION_TTL_SECONDS', '86400'),
    'payment_mode' => EnvLoader::get('PAYMENT_MODE', 'demo'),
    'payment_partner' => [
        'provider' => EnvLoader::get('PAYMENT_PARTNER_PROVIDER', 'NOT_CONFIGURED'),
        'key_id' => EnvLoader::get('PAYMENT_PARTNER_KEY_ID', 'NOT_CONFIGURED'),
        'webhook_secret' => EnvLoader::get('PAYMENT_WEBHOOK_SECRET', 'NOT_CONFIGURED'),
    ],
    'rate_limit' => [
        'max_requests' => 120,
        'window_seconds' => 60,
    ],
    'uploads_dir' => dirname(__DIR__) . '/uploads',
    'logs_dir' => dirname(__DIR__) . '/logs',
];

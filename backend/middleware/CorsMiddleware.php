<?php
declare(strict_types=1);

final class CorsMiddleware
{
    public static function handle(array $config): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigin = $config['frontend_origin'] ?? 'http://localhost:5173';

        if ($origin !== '' && ($origin === $allowedOrigin || str_starts_with($origin, 'http://localhost') || str_starts_with($origin, 'http://127.0.0.1'))) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Vary: Origin');
            header('Access-Control-Allow-Credentials: true');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, Idempotency-Key');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}

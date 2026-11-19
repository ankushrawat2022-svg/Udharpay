<?php
declare(strict_types=1);

final class Response
{
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): never
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => gmdate('c'),
        ], $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): never
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => gmdate('c'),
        ], $status);
    }
}

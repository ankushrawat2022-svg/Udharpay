<?php
declare(strict_types=1);

final class CsrfMiddleware
{
    public static function verify(array $user): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        // Only strictly enforce CSRF header when authenticating via cookies
        if (empty($_SERVER['HTTP_AUTHORIZATION']) && !empty($_COOKIE['udharpay_session'])) {
            $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            $expected = (string)($user['csrf_token'] ?? '');
            if ($provided === '' || !hash_equals($expected, $provided)) {
                Response::error('Invalid or missing CSRF token.', 403);
            }
        }
    }
}

<?php
declare(strict_types=1);

final class AuthMiddleware
{
    public static function authenticate(): array
    {
        $token = self::extractToken();
        if ($token === null) {
            Response::error('Authentication required.', 401);
        }

        $tokenHash = Security::sha256($token);
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT s.id AS session_id, s.csrf_token, u.id, u.full_name, u.phone, u.email, u.is_demo, u.status, r.slug AS role
             FROM user_sessions s
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN roles r ON r.id = u.role_id
             WHERE s.token_hash = :token_hash
               AND s.revoked_at IS NULL
               AND s.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => $tokenHash]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('Session expired or invalid. Please sign in again.', 401);
        }

        if ($user['status'] !== 'active') {
            Response::error('Your account is currently not active.', 403);
        }

        return $user;
    }

    public static function extractToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+([A-Fa-f0-9]{32,128})$/', trim($header), $m)) {
            return $m[1];
        }
        if (!empty($_COOKIE['udharpay_session'])) {
            return (string)$_COOKIE['udharpay_session'];
        }
        return null;
    }
}

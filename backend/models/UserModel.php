<?php
declare(strict_types=1);

final class UserModel
{
    public static function findByPhoneOrEmail(string $identifier): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT u.*, r.slug AS role
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.phone = :phone OR u.email = :email
             LIMIT 1'
        );
        $stmt->execute(['phone' => $identifier, 'email' => $identifier]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function createSession(int $userId, int $ttlSeconds): array
    {
        $pdo = Database::connection();
        $plainToken = Security::randomToken(32);
        $tokenHash = Security::sha256($plainToken);
        $csrfToken = Security::randomToken(32);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

        $stmt = $pdo->prepare(
            'INSERT INTO user_sessions (user_id, token_hash, csrf_token, ip_address, user_agent, expires_at)
             VALUES (:user_id, :token_hash, :csrf_token, :ip, :ua, :expires_at)'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'token_hash' => $tokenHash,
            'csrf_token' => $csrfToken,
            'ip'         => Security::clientIp(),
            'ua'         => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
            'expires_at' => $expiresAt,
        ]);

        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $userId]);

        return [
            'token' => $plainToken,
            'csrf_token' => $csrfToken,
            'expires_at' => $expiresAt,
        ];
    }

    public static function revokeSession(int $sessionId): void
    {
        $pdo = Database::connection();
        $pdo->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE id = :id')->execute(['id' => $sessionId]);
    }
}

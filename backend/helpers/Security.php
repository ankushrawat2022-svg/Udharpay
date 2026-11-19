<?php
declare(strict_types=1);

final class Security
{
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function sha256(string $value): string
    {
        return hash('sha256', $value);
    }

    public static function generateReference(string $prefix): string
    {
        return sprintf('%s-%s-%s', strtoupper($prefix), date('Ymd'), strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)));
    }

    public static function clientIp(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function verifyHmacSignature(string $payload, string $signatureHeader, string $secret): bool
    {
        if ($secret === '' || $secret === 'NOT_CONFIGURED' || $signatureHeader === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, trim($signatureHeader));
    }
}

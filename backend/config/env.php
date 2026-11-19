<?php
declare(strict_types=1);

/**
 * Loads environment variables from a .env file stored outside public webroot
 * (falls back to backend/.env if parent .env is absent).
 */
final class EnvLoader
{
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        $candidates = [
            dirname(__DIR__, 2) . '/.env',
            dirname(__DIR__) . '/.env',
        ];
        foreach ($candidates as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                    continue;
                }
                [$key, $val] = explode('=', $trimmed, 2);
                $key = trim($key);
                $val = trim($val, " \t\n\r\0\x0B\"'");
                if ($key !== '' && getenv($key) === false) {
                    putenv("{$key}={$val}");
                    $_ENV[$key] = $val;
                }
            }
            break;
        }
        self::$loaded = true;
    }

    public static function get(string $key, string $default = ''): string
    {
        self::load();
        $val = getenv($key);
        return $val === false ? $default : (string)$val;
    }
}

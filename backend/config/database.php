<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = EnvLoader::get('DB_HOST', '127.0.0.1');
        $port = EnvLoader::get('DB_PORT', '3306');
        $name = EnvLoader::get('DB_NAME', 'udharpay_db');
        $user = EnvLoader::get('DB_USER', 'root');
        $pass = EnvLoader::get('DB_PASS', '');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+05:30'",
        ];

        self::$pdo = new PDO($dsn, $user, $pass, $options);
        return self::$pdo;
    }

    public static function healthCheck(): array
    {
        try {
            $pdo = self::connection();
            $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
            $tablesCount = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
            return [
                'connected' => true,
                'engine' => 'MariaDB / MySQL',
                'version' => $version,
                'tables_count' => $tablesCount,
            ];
        } catch (Throwable $e) {
            return [
                'connected' => false,
                'error' => 'Database connection failed. Check backend/.env credentials.',
            ];
        }
    }
}

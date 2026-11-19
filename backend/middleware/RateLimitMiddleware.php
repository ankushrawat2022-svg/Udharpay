<?php
declare(strict_types=1);

final class RateLimitMiddleware
{
    public static function handle(array $config): void
    {
        $ip = Security::clientIp();
        $max = (int)($config['rate_limit']['max_requests'] ?? 120);
        $window = (int)($config['rate_limit']['window_seconds'] ?? 60);
        $dir = ($config['logs_dir'] ?? dirname(__DIR__) . '/logs') . '/ratelimit';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $bucketFile = $dir . '/' . md5($ip) . '.json';
        $now = time();
        $data = ['count' => 0, 'reset_at' => $now + $window];

        if (is_file($bucketFile)) {
            $raw = @file_get_contents($bucketFile);
            $decoded = $raw ? json_decode($raw, true) : null;
            if (is_array($decoded) && ($decoded['reset_at'] ?? 0) > $now) {
                $data = $decoded;
            }
        }

        $data['count']++;
        @file_put_contents($bucketFile, json_encode($data), LOCK_EX);

        header('X-RateLimit-Limit: ' . $max);
        header('X-RateLimit-Remaining: ' . max(0, $max - $data['count']));

        if ($data['count'] > $max) {
            Response::error('Too many requests. Please slow down and try again shortly.', 429);
        }
    }
}

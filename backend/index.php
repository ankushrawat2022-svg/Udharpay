<?php
declare(strict_types=1);

/**
 * UdharPay Core PHP 8.3+ Front Controller (Apache / AWebServer compatible)
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);

$config = require __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';

foreach (['helpers', 'middleware', 'services', 'models', 'controllers'] as $dir) {
    foreach (glob(__DIR__ . "/{$dir}/*.php") ?: [] as $file) {
        require_once $file;
    }
}
require_once __DIR__ . '/routes/api.php';

set_exception_handler(static function (Throwable $e) use ($config): void {
    $line = sprintf("[%s] %s in %s:%d\n", date('c'), $e->getMessage(), $e->getFile(), $e->getLine());
    @file_put_contents(__DIR__ . '/logs/error.log', $line, FILE_APPEND | LOCK_EX);
    Response::error(
        $config['debug'] ? $e->getMessage() : 'An internal server error occurred.',
        500
    );
});

CorsMiddleware::handle($config);
RateLimitMiddleware::handle($config);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Strip subfolder prefix when hosted inside /UdharPay/backend on Apache/AWebServer
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($uriPath, $scriptDir)) {
    $uriPath = substr($uriPath, strlen($scriptDir)) ?: '/';
}

ApiRouter::dispatch($method, $uriPath, $config);

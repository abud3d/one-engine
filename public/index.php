<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    require dirname(__DIR__) . '/bootstrap.php';
    $site = \OneEngine\Modules\Runtime\Site::fromArray(require dirname(__DIR__) . '/config/site.php');
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    (new \OneEngine\Modules\Runtime\Application($site, __DIR__))
        ->handle($method, $_SERVER['REQUEST_URI'] ?? '/', $https)->send($method === 'HEAD');
} catch (Throwable $error) {
    error_log('ONE Engine request failed: ' . $error::class);
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    header('X-Robots-Tag: noindex, nofollow');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
        echo 'Service unavailable';
    }
}

<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use OneEngine\Modules\CentralManagement\WebInstaller;
use OneEngine\Security\Headers;

$https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
foreach (Headers::defaults($https) as $name => $value) {
    if ($name === 'Content-Security-Policy') $value = "default-src 'none'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'";
    header($name . ': ' . $value);
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
try {
    echo (new WebInstaller(dirname(__DIR__)))->run($_SERVER['REQUEST_METHOD'] ?? 'GET', $_POST, $_SERVER);
} catch (Throwable $error) {
    error_log('ONE setup failed: ' . $error::class);
    http_response_code($error instanceof DomainException ? 403 : ($error instanceof InvalidArgumentException ? 422 : 503));
    echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>تعذر التثبيت</title><p>تعذر إكمال التثبيت بأمان. راجع القيم والأذونات ثم أعد المحاولة.</p><a href="/setup">عودة</a></html>';
}

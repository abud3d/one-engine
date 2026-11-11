<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use OneEngine\Modules\CentralManagement\AdminApplication;
use OneEngine\Modules\Content\SiteContent;
use OneEngine\Modules\Identity\{IdentityService, SessionGuard};
use OneEngine\Modules\Runtime\Site;
use OneEngine\Security\Headers;
use OneEngine\Storage\JsonStore;

$https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '';
foreach (Headers::defaults($https) as $name => $value) {
    if ($name === 'Content-Security-Policy') {
        $value = "default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self' https:; base-uri 'none'; form-action 'self'; frame-ancestors 'none'";
    }
    header($name . ': ' . $value);
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
try {
    $private = \OneEngine\Modules\Runtime\LocalConfiguration::privateDirectory(dirname(__DIR__));
    if ($private === null) {
        throw new RuntimeException('ONE_PRIVATE_DIR is required for administration.');
    }
    SessionGuard::start($https);
    $site = Site::fromArray(require dirname(__DIR__) . '/config/site.php');
    $store = new JsonStore($private, __DIR__);
    echo (new AdminApplication($site->id, new IdentityService($store), new SiteContent($store)))
        ->run($_SERVER['REQUEST_METHOD'] ?? 'GET', '/admin', $_POST, $_GET);
} catch (Throwable $error) {
    error_log('ONE admin failed: ' . $error::class);
    http_response_code($error instanceof DomainException ? 403 : ($error instanceof InvalidArgumentException ? 422 : 503));
    echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>تعذر الطلب</title><p>تعذر تنفيذ الطلب بأمان.</p><a href="/admin">عودة</a></html>';
}

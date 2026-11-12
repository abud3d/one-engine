<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use OneEngine\Modules\CentralManagement\AdminApplication;
use OneEngine\Modules\CentralManagement\{AuditLog, BackupService, OperationLock};
use OneEngine\Modules\Content\SiteContent;
use OneEngine\Modules\Identity\{IdentityService, SessionGuard};
use OneEngine\Modules\Runtime\Site;
use OneEngine\Security\Headers;
use OneEngine\Security\OriginGuard;
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
    $root = dirname(__DIR__);
    $private = \OneEngine\Modules\Runtime\LocalConfiguration::privateDirectory($root);
    if ($private === null) {
        throw new RuntimeException('ONE_PRIVATE_DIR is required for administration.');
    }
    $trustedOrigin = \OneEngine\Modules\Runtime\LocalConfiguration::trustedOrigin($root);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') OriginGuard::requireAllowed($trustedOrigin, $_SERVER);
    SessionGuard::start($https || $trustedOrigin !== null);
    $site = Site::fromArray(require $root . '/config/site.php');
    $store = new JsonStore($private, __DIR__);
    $identity = new IdentityService($store);
    $actor = SessionGuard::actor($site->id);
    if ($actor !== null) {
        $validated = $identity->validateSession($site->id, $actor);
        if ($validated === null) SessionGuard::invalidate(); else SessionGuard::refresh($validated);
    }
    $lock = new OperationLock($private);
    echo (new AdminApplication($site->id, $identity, new SiteContent($store), new AuditLog($store, $lock), new BackupService($store, $private, $lock), $lock, $site->toArray()))
        ->run($_SERVER['REQUEST_METHOD'] ?? 'GET', '/admin', $_POST, $_GET);
} catch (Throwable $error) {
    error_log('ONE admin failed: ' . $error::class);
    http_response_code($error instanceof DomainException ? 403 : ($error instanceof InvalidArgumentException ? 422 : 503));
    $message = $error instanceof DomainException ? 'لا تملك الصلاحية أو انتهت صلاحية الطلب.' : ($error instanceof InvalidArgumentException ? 'تحقق من الحقول والقيم المدخلة.' : 'الخدمة غير متاحة مؤقتًا. لم تُحفظ تغييرات غير مكتملة.');
    echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>تعذر الطلب</title><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><a href="/admin">عودة</a></html>';
}

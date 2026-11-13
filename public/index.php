<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    require dirname(__DIR__) . '/bootstrap.php';
    $siteConfig = require dirname(__DIR__) . '/config/site.php';
    $site = \OneEngine\Modules\Runtime\Site::fromArray($siteConfig);
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    // Optional private JSON storage; public index and community stay reachable without it.
    $pages = null;
    $community = null;
    $cms = null;
    $actor = null;
    $private = \OneEngine\Modules\Runtime\LocalConfiguration::privateDirectory(dirname(__DIR__));
    if ($private !== null) {
        $store = \OneEngine\Storage\StoreFactory::create(\OneEngine\Modules\Runtime\LocalConfiguration::storageType(dirname(__DIR__)), $private, __DIR__);
        $pages = new \OneEngine\Modules\Runtime\PageCatalog($store);
        $community = new \OneEngine\Modules\Runtime\CommunityCatalog($store);
        $cms = new \OneEngine\Modules\Content\SiteContent($store);
        $settings = $cms->settings($site->id);
        if ($settings !== []) $site = \OneEngine\Modules\Runtime\Site::fromArray(array_merge($siteConfig, $settings));
        if (isset($_COOKIE['ONESESSID'])) {
            \OneEngine\Modules\Identity\SessionGuard::start($https);
            $actor = \OneEngine\Modules\Identity\SessionGuard::actor($site->id);
            if ($actor !== null) {
                $validated = (new \OneEngine\Modules\Identity\IdentityService($store))->validateSession($site->id, $actor);
                if ($validated === null) {
                    \OneEngine\Modules\Identity\SessionGuard::invalidate();
                    $actor = null;
                } else {
                    \OneEngine\Modules\Identity\SessionGuard::refresh($validated);
                    $actor = $validated;
                }
            }
        }
    }
    (new \OneEngine\Modules\Runtime\Application($site, __DIR__, $pages, $community, $cms, $actor))
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

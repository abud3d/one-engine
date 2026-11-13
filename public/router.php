<?php
declare(strict_types=1);

// PHP development server only. Never return false for arbitrary paths.
// This explicit legacy mount is for Codespaces preview, not a production Apache rewrite.
$path = explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0];
if ($path === '/admin' || $path === '/admin/') {
    require __DIR__ . '/admin.php';
    return;
}
if ($path === '/setup' || $path === '/setup/') {
    require __DIR__ . '/setup.php';
    return;
}
if ($path === '/assets/setup.css') {
    header('Content-Type: text/css; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    readfile(__DIR__ . '/assets/setup.css');
    return;
}
if ($path === '/assets/admin.css') {
    header('Content-Type: text/css; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    readfile(__DIR__ . '/assets/admin.css');
    return;
}
if ($path === '/assets/admin.js') {
    header('Content-Type: text/javascript; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    readfile(__DIR__ . '/assets/admin.js');
    return;
}
// Mounted ARBNT application: explicit file allowlist, not a directory passthrough.
// The legacy API is unchanged in functionality; its PHP core lives outside public/.
if (is_file(dirname(__DIR__) . '/src/LegacyArbnt/core.php') && ($path === '/community' || $path === '/community/')) {
    header('Location: /community/community.html', true, 302);
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    return;
}
if (is_file(dirname(__DIR__) . '/src/LegacyArbnt/core.php') && str_starts_with($path, '/community/')) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $html = preg_match('~\\A/community/(?:admin|communities|community|favorites|forgot|index|login|post|profile|publish|register)\\.html\\z~D', $path);
    $asset = preg_match('~\\A/community/assets/[a-z][a-z0-9-]*\\.(?:css|js)\\z~D', $path);
    $api = $path === '/community/api/community.php';
    if ((!$html && !$asset && !$api) || (!in_array($method, ['GET', 'HEAD'], true) && !$api)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo 'Not found';
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store');
    if ($api) {
        // A request to the PHP endpoint must execute, never be served as source.
        $_SERVER['SCRIPT_NAME'] = '/community/api/community.php';
        require __DIR__ . '/community/api/community.php';
        return;
    }
    $full = __DIR__ . $path;
    if (!is_file($full) || is_link($full)) {
        http_response_code(404);
        echo 'Not found';
        return;
    }
    $type = $html ? 'text/html' : (str_ends_with($path, '.css') ? 'text/css' : 'text/javascript');
    header('Content-Type: ' . $type . '; charset=utf-8');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' https: data:; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'");
    if ($method !== 'HEAD') {
        readfile($full);
    }
    return;
}

if (in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)
    && $path === '/' && is_file(__DIR__ . '/index.html')) {
    require dirname(__DIR__) . '/bootstrap.php';
    foreach (\OneEngine\Security\Headers::defaults(false) as $name => $value) {
        header($name . ': ' . $value);
    }
    header('Content-Type: text/html; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        readfile(__DIR__ . '/index.html');
    }
    return;
}
require __DIR__ . '/index.php';

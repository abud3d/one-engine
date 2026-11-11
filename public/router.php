<?php
declare(strict_types=1);

// PHP development server only. Never return false for arbitrary paths.
$path = explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0];
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

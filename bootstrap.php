<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80300) {
    throw new RuntimeException('ONE Engine requires PHP 8.3+.');
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'OneEngine\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    if (!preg_match('/\A[A-Za-z][A-Za-z0-9\\\\]*\z/', $relative)) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

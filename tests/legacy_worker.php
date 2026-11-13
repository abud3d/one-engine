<?php
declare(strict_types=1);

$publicRoot = $argv[1] ?? '';
$operation = $argv[2] ?? '';
if ($publicRoot === '' || !is_dir($publicRoot)) {
    fwrite(STDERR, "Invalid public root\n");
    exit(2);
}

define('ARBNT_PUBLIC_ROOT', $publicRoot);
require dirname(__DIR__) . '/src/LegacyArbnt/core.php';

try {
    if ($operation === 'read') {
        echo json_encode(arbnt_read('posts'), JSON_THROW_ON_ERROR);
        exit;
    }
    if ($operation === 'replace') {
        arbnt_write('posts', static fn (array $rows): array => [['id' => 'replacement']]);
        exit;
    }
    if ($operation === 'increment') {
        for ($i = 0; $i < 25; $i++) {
            arbnt_write('posts', static fn (array $rows): array => ['count' => ($rows['count'] ?? 0) + 1]);
        }
        exit;
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error::class . "\n");
    exit(1);
}

fwrite(STDERR, "Unknown operation\n");
exit(2);

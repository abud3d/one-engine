<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$class = $argv[1] === 'json' ? \OneEngine\Storage\JsonStore::class : \OneEngine\Storage\SqliteStore::class;
$store = new $class($argv[2], dirname(__DIR__) . '/public');
for ($i = 0; $i < 25; $i++) {
    $store->update('test', 'counter', static fn (array $old): array => ['n' => ($old['n'] ?? 0) + 1]);
}

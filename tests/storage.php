<?php
declare(strict_types=1);

use OneEngine\Storage\{JsonStore, SqliteStore};

foreach (['json' => JsonStore::class, 'sqlite' => SqliteStore::class] as $driver => $class) {
    test("$driver: isolated read/modify/write, rollback and validation", function () use ($temp, $driver, $class): void {
        $directory = $temp . '/' . $driver;
        mkdir($directory, 0700);
        $store = new $class($directory, dirname(__DIR__) . '/public');
        check($store->read('site-a', 'objects') === []);
        $store->update('site-a', 'objects', fn (array $old): array => ['text' => 'نص تجريبي', 'count' => 1]);
        $store->update('site-a', 'objects', fn (array $old): array => [...$old, 'count' => $old['count'] + 1]);
        check($store->read('site-a', 'objects') === ['text' => 'نص تجريبي', 'count' => 2]);
        check($store->read('site-b', 'objects') === []);
        rejects(fn () => $store->update('site-a', 'objects', fn () => throw new LogicException('abort')), LogicException::class);
        rejects(fn () => $store->update('site-a', 'objects', fn () => 'not an array'), UnexpectedValueException::class);
        rejects(fn () => $store->update('site-a', 'objects', fn () => ['invalid' => "\xff"]), JsonException::class);
        rejects(fn () => $store->update('site-a', 'objects', fn () => [str_repeat('x', 2097153)]), LengthException::class);
        $nested = [];
        for ($depth = 0; $depth < 70; $depth++) {
            $nested = [$nested];
        }
        rejects(fn () => $store->update('site-a', 'objects', fn () => $nested), JsonException::class);
        check($store->read('site-a', 'objects')['count'] === 2);
        rejects(fn () => $store->read('../a', 'objects'), InvalidArgumentException::class);
        rejects(fn () => $store->update('a', '../../b', fn () => []), InvalidArgumentException::class);
        $store->update('a-b', 'c', fn () => [1]);
        check($store->read('a', 'b-c') === []);
    });
    test("$driver: concurrent increments do not lose updates", function () use ($temp, $driver): void {
        $directory = $temp . '/' . $driver . '-concurrent';
        mkdir($directory, 0700);
        $processes = [];
        for ($i = 0; $i < 4; $i++) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/worker.php', $driver, $directory], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            check(is_resource($process));
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }
        foreach ($processes as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            check(proc_close($process) === 0, $output);
        }
        $class = $driver === 'json' ? JsonStore::class : SqliteStore::class;
        $store = new $class($directory, dirname(__DIR__) . '/public');
        check($store->read('test', 'counter')['n'] === 100);
    });
}

test('JSON corruption is not converted to an empty document or overwritten', function () use ($temp): void {
    $directory = $temp . '/corrupt';
    mkdir($directory, 0700);
    $store = new JsonStore($directory, dirname(__DIR__) . '/public');
    $store->update('test', 'data', fn () => ['keep' => true]);
    $path = $directory . '/4-test-data.json';
    file_put_contents($path, '{broken');
    rejects(fn () => $store->read('test', 'data'), JsonException::class);
    rejects(fn () => $store->update('test', 'data', fn () => []), JsonException::class);
    check(file_get_contents($path) === '{broken');
    file_put_contents($path, 'null');
    rejects(fn () => $store->read('test', 'data'), UnexpectedValueException::class);
});
test('JSON contention has a bounded deadline', function () use ($temp): void {
    $directory = $temp . '/timeout';
    mkdir($directory, 0700);
    $store = new JsonStore($directory, dirname(__DIR__) . '/public', 30);
    $lock = fopen($directory . '/4-test-data.lock', 'c');
    flock($lock, LOCK_EX);
    try {
        $start = hrtime(true);
        rejects(fn () => $store->update('test', 'data', fn () => []), RuntimeException::class);
        check((hrtime(true) - $start) / 1e9 < 1.0);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
});
test('storage refuses public roots, permissive directories and symlink files', function () use ($temp): void {
    $public = $temp . '/public';
    mkdir($public, 0700);
    foreach ([JsonStore::class, SqliteStore::class] as $class) {
        rejects(fn () => new $class($public, $public), InvalidArgumentException::class);
    }
    $private = $temp . '/private';
    mkdir($private, 0700);
    chmod($private, 0755);
    rejects(fn () => new JsonStore($private, $public), RuntimeException::class);
    chmod($private, 0700);
    symlink($temp . '/missing', $private . '/4-test-data.json');
    $store = new JsonStore($private, $public);
    rejects(fn () => $store->read('test', 'data'), RuntimeException::class);
    symlink($temp . '/missing', $private . '/documents.sqlite');
    rejects(fn () => new SqliteStore($private, $public), RuntimeException::class);
});

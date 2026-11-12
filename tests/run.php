<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$tests = [];
$temp = sys_get_temp_dir() . '/one-tests-' . bin2hex(random_bytes(8));
mkdir($temp, 0700);

function test(string $name, callable $work): void
{
    global $tests;
    $tests[$name] = $work;
}

function check(bool $condition, string $message = 'Check failed'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rejects(callable $work, string $class = Throwable::class): void
{
    try {
        $work();
    } catch (Throwable $error) {
        check($error instanceof $class, 'Unexpected exception: ' . $error::class);
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $class);
}

function removeTree(string $path): void
{
    if (is_link($path) || !is_dir($path)) {
        unlink($path);
        return;
    }
    foreach (new DirectoryIterator($path) as $file) {
        if (!$file->isDot()) {
            removeTree($file->getPathname());
        }
    }
    rmdir($path);
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

foreach (['core', 'storage', 'runtime', 'pages', 'cms', 'operations', 'package', 'doctor', 'http', 'legacy'] as $suite) {
    require __DIR__ . '/' . $suite . '.php';
}
$failed = 0;
try {
    foreach ($tests as $name => $work) {
        try {
            $work();
            echo "PASS $name\n";
        } catch (Throwable $error) {
            $failed++;
            fwrite(STDERR, "FAIL $name: " . $error->getMessage() . "\n");
        }
    }
} finally {
    removeTree($temp);
}
echo count($tests) . " tests, $failed failures\n";
exit($failed === 0 ? 0 : 1);

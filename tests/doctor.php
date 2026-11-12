<?php
declare(strict_types=1);

test('CMS doctor reports incomplete install without leaking settings', static function (): void {
    global $temp;
    $root = $temp . '/doctor-fixture';
    mkdir($root . '/bin', 0700, true);
    mkdir($root . '/public', 0700, true);
    mkdir($root . '/config', 0700, true);
    mkdir($root . '/private', 0700, true);
    copy(dirname(__DIR__) . '/bin/one-doctor', $root . '/bin/one-doctor');
    file_put_contents($root . '/public/.htaccess', "# test\n");
    file_put_contents($root . '/config/site.php', "<?php return [];\n");
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/bin/one-doctor') . ' 2>&1';
    exec($command, $lines, $status);
    check($status === 2, 'Doctor must fail closed before installation.');
    check(str_contains(implode("\n", $lines), 'Local installation configuration'), 'Missing config must be diagnosed.');

    $private = realpath($root . '/private');
    check(is_string($private));
    $config = ['private_dir' => $private, 'trusted_origin' => 'https://example.invalid'];
    file_put_contents($root . '/config/local.php', "<?php return " . var_export($config, true) . ";\n");
    chmod($root . '/config/local.php', 0600);
    chmod($private, 0700);
    $lines = [];
    exec($command, $lines, $status);
    check($status === 0, 'Doctor must pass a valid local fixture: ' . implode("\n", $lines));
    check(str_contains(implode("\n", $lines), 'LOCAL CHECKS PASS'));
    check(!str_contains(implode("\n", $lines), $private), 'Do not expose local private paths.');

    chmod($private, 0755);
    $lines = [];
    exec($command, $lines, $status);
    check($status === 2, 'Doctor must reject permissive private directory.');
    check(str_contains(implode("\n", $lines), 'grants group/other access'));
});

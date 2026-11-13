<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80300 || count($argv) !== 3 || !class_exists(ZipArchive::class)) {
    fwrite(STDERR, "Usage: php tests/cms_package_acceptance.php PACKAGE.zip NEW_WORK_DIRECTORY\n");
    exit(2);
}

function requireCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function request(string $url, string $method = 'GET', array $data = [], string $cookie = '', string $origin = 'https://cms.example.test'): array
{
    $headers = "Host: attacker.invalid\r\nOrigin: {$origin}\r\n";
    if ($cookie !== '') {
        $headers .= 'Cookie: ' . $cookie . "\r\n";
    }
    if ($method === 'POST') {
        $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
    }
    $context = stream_context_create(['http' => [
        'method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5,
        'header' => $headers, 'content' => $method === 'POST' ? http_build_query($data) : '',
    ]]);
    $body = file_get_contents($url, false, $context);
    requireCheck(is_string($body), 'HTTP request failed.');
    return [$body, $http_response_header];
}

$archive = realpath($argv[1]);
requireCheck(is_string($archive), 'CMS archive not found.');
$work = $argv[2];
requireCheck(!file_exists($work) && mkdir($work, 0700, true), 'Choose a new acceptance directory.');
$work = realpath($work);
requireCheck(is_string($work), 'Cannot resolve acceptance directory.');
$zip = new ZipArchive();
requireCheck($zip->open($archive) === true, 'Cannot open CMS archive.');
try {
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        requireCheck(is_string($name) && str_starts_with($name, 'one-engine-cms/') && !str_contains($name, '../'), 'Unsafe ZIP entry.');
    }
    requireCheck($zip->extractTo($work), 'Cannot extract CMS archive.');
} finally {
    $zip->close();
}
$root = $work . '/one-engine-cms';
requireCheck(!is_dir($root . '/private') && !is_file($root . '/config/local.php'), 'Archive contains private state.');
$manifest = json_decode((string) file_get_contents($root . '/package.json'), true, 32, JSON_THROW_ON_ERROR);
$version = require $root . '/config/version.php';
requireCheck(($manifest['engine'] ?? null) === ($version['version'] ?? null), 'Package and engine versions differ.');
$password = 'acceptance password 2026';

$socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
requireCheck(is_resource($socket), $errorMessage);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$server = proc_open([PHP_BINARY, '-c', $root . '/config/php.ini', '-S', $address, '-t', $root . '/public', $root . '/public/router.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $work . '/server.log', 'a'], 2 => ['file', $work . '/server.log', 'a']], $serverPipes, $root, array_merge(getenv(), ['ONE_TEST_HTTPS' => '1']));
requireCheck(is_resource($server), 'Cannot start packaged CMS.');
fclose($serverPipes[0]);
try {
    $ready = false;
    for ($i = 0; $i < 100; $i++) {
        set_error_handler(static fn (): bool => true);
        try {
            $probe = stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 0.05);
        } finally {
            restore_error_handler();
        }
        if (is_resource($probe)) {
            fclose($probe);
            $ready = true;
            break;
        }
        usleep(10000);
    }
    requireCheck($ready, 'Packaged CMS server did not start.');
    $base = 'http://' . $address;
    [$setup, $headers] = request($base . '/setup');
    requireCheck(str_contains($setup, 'ONE Engine') && str_contains($setup, 'v' . $version['version']) && str_contains($setup, (string) $version['build']), 'Setup page or version is missing.');
    requireCheck(preg_match('/name="csrf" value="([a-f0-9]{64})"/', $setup, $setupToken) === 1, 'Setup CSRF missing.');
    requireCheck(preg_match('/Set-Cookie:\s*(ONESETUP=[^;]+)/i', implode("\n", $headers), $setupCookie) === 1, 'Setup session cookie missing.');
    [$installed, $headers] = request($base . '/setup', 'POST', ['csrf' => $setupToken[1], 'site_name' => 'Installed CMS', 'site_url' => 'https://cms.example.test', 'language' => 'ar', 'username' => 'admin.cms', 'email' => 'admin@example.test', 'password' => $password, 'password_confirm' => $password], $setupCookie[1]);
    requireCheck(str_contains($installed, 'تم تثبيت ONE Engine بنجاح'), 'Browser installation failed.');
    requireCheck(is_file($root . '/config/local.php'), 'Web installer did not create local configuration.');
    $local = require $root . '/config/local.php';
    $private = (string) ($local['private_dir'] ?? '');
    requireCheck(($local['storage'] ?? null) === 'json' && is_dir($private), 'JSON storage was not configured.');
    requireCheck((fileperms($root . '/config/local.php') & 0777) === 0600 && (fileperms($private) & 0777) === 0700, 'Installed permissions are unsafe.');
    [$lockedSetup] = request($base . '/setup');
    requireCheck(str_contains($lockedSetup, 'مثبت بالفعل') && !str_contains($lockedSetup, 'name="password"'), 'Setup was not locked after installation.');
    [$login, $headers] = request($base . '/admin');
    requireCheck(preg_match('/name="csrf" value="([a-f0-9]{64})"/', $login, $token) === 1, 'Login CSRF missing.');
    requireCheck(preg_match('/Set-Cookie:\s*(ONESESSID=[^;]+)/i', implode("\n", $headers), $cookieMatch) === 1, 'Session cookie missing.');
    $cookie = $cookieMatch[1];
    [, $foreignHeaders] = request($base . '/admin', 'POST', ['csrf' => $token[1], 'action' => 'login', 'username' => 'admin.cms', 'password' => $password], $cookie, 'https://evil.example');
    requireCheck(str_contains(implode("\n", $foreignHeaders), '403 Forbidden'), 'Foreign Origin was not rejected.');
    [, $headers] = request($base . '/admin', 'POST', ['csrf' => $token[1], 'action' => 'login', 'username' => 'admin.cms', 'password' => $password], $cookie);
    requireCheck(str_contains(implode("\n", $headers), '303 See Other'), 'Packaged admin login failed.');
    if (preg_match('/Set-Cookie:\s*(ONESESSID=[^;]+)/i', implode("\n", $headers), $rotated) === 1) {
        $cookie = $rotated[1];
    }
    [$dashboard] = request($base . '/admin', 'GET', [], $cookie);
    requireCheck(str_contains($dashboard, 'ONE Engine v' . $version['version']) && str_contains($dashboard, (string) $version['build']), 'Admin version information missing.');
    requireCheck(preg_match('/name="csrf" value="([a-f0-9]{64})"/', $dashboard, $token) === 1, 'Dashboard CSRF missing.');
    [$editor] = request($base . '/admin?view=pages', 'GET', [], $cookie);
    requireCheck(str_contains($editor, 'name="block_type[]"') && !str_contains($editor, 'name="blocks"'), 'Visual block editor missing.');
    request($base . '/admin', 'POST', ['csrf' => $token[1], 'action' => 'settings', 'title' => 'Installed CMS', 'description' => 'Acceptance site', 'base_url' => 'https://cms.example.test', 'indexable' => '1'], $cookie);
    [$dashboard] = request($base . '/admin', 'GET', [], $cookie);
    preg_match('/name="csrf" value="([a-f0-9]{64})"/', $dashboard, $token);
    request($base . '/admin', 'POST', ['csrf' => $token[1], 'action' => 'section', 'id' => 'acceptance', 'title' => 'Acceptance', 'description' => '', 'layout' => 'one', 'visibility' => 'public', 'state' => 'published'], $cookie);
    [$dashboard] = request($base . '/admin', 'GET', [], $cookie);
    preg_match('/name="csrf" value="([a-f0-9]{64})"/', $dashboard, $token);
    request($base . '/admin', 'POST', ['csrf' => $token[1], 'action' => 'template', 'id' => 'acceptance-template', 'title' => 'Acceptance template', 'description' => '', 'layout' => 'three', 'sidebar' => '1'], $cookie);
    [$dashboard] = request($base . '/admin', 'GET', [], $cookie);
    preg_match('/name="csrf" value="([a-f0-9]{64})"/', $dashboard, $token);
    request($base . '/admin', 'POST', ['csrf' => $token[1], 'action' => 'media', 'id' => 'hero', 'title' => 'Hero', 'alt' => 'Hero image', 'url' => 'https://cdn.example.test/hero.webp'], $cookie);
    [$dashboard] = request($base . '/admin', 'GET', [], $cookie);
    preg_match('/name="csrf" value="([a-f0-9]{64})"/', $dashboard, $token);
    $page = ['csrf' => $token[1], 'action' => 'page', 'id' => 'installed-page', 'title' => 'Installed page', 'description' => 'Acceptance', 'body' => 'Created inside packaged CMS', 'section_id' => 'acceptance', 'template_id' => 'acceptance-template', 'block_type' => ['heading', 'image'], 'block_content' => ['Real block', 'https://cdn.example.test/hero.webp'], 'block_visibility' => ['public', 'public'], 'block_column' => ['1', '2'], 'allowed_users' => [], 'allowed_editors' => [], 'layout' => 'two', 'visibility' => 'public', 'state' => 'draft'];
    request($base . '/admin', 'POST', $page, $cookie);
    [$preview, $headers] = request($base . '/preview/installed-page', 'GET', [], $cookie);
    requireCheck(str_contains(implode("\n", $headers), '200 OK') && str_contains($preview, 'Real block'), 'Draft preview failed.');
    [, $headers] = request($base . '/p/installed-page');
    requireCheck(str_contains(implode("\n", $headers), '404 Not Found'), 'Anonymous visitor saw draft.');
    [$dashboard] = request($base . '/admin', 'GET', [], $cookie);
    preg_match('/name="csrf" value="([a-f0-9]{64})"/', $dashboard, $token);
    $page['csrf'] = $token[1];
    $page['state'] = 'published';
    request($base . '/admin', 'POST', $page, $cookie);
    [$dashboard] = request($base . '/admin', 'GET', [], $cookie);
    preg_match('/name="csrf" value="([a-f0-9]{64})"/', $dashboard, $token);
    request($base . '/admin', 'POST', ['csrf' => $token[1], 'action' => 'menu', 'id' => 'installed-link', 'label' => 'Installed menu', 'url' => '/p/installed-page', 'position' => 'header', 'visibility' => 'public'], $cookie);
    [$public, $headers] = request($base . '/p/installed-page');
    requireCheck(str_contains(implode("\n", $headers), '200 OK') && str_contains($public, 'Created inside packaged CMS'), 'Published page unavailable.');
    requireCheck(str_contains($public, 'Installed CMS') && str_contains($public, 'layout-three') && str_contains($public, 'cdn.example.test/hero.webp') && str_contains($public, 'Installed menu'), 'Managed settings, template, media or menu missing from public site.');
    [$dashboard] = request($base . '/admin', 'GET', [], $cookie);
    preg_match('/name="csrf" value="([a-f0-9]{64})"/', $dashboard, $token);
    [$backupPage] = request($base . '/admin', 'POST', ['csrf' => $token[1], 'action' => 'backup_create'], $cookie);
    requireCheck(str_contains($backupPage, 'تم إنشاء النسخة الاحتياطية'), 'Packaged backup creation failed.');
    [$backups] = request($base . '/admin?view=backups', 'GET', [], $cookie);
    requireCheck(str_contains($backups, '.json'), 'Packaged backup is not listed.');
    [$audit] = request($base . '/admin?view=audit', 'GET', [], $cookie);
    requireCheck(str_contains($audit, 'content.page') && str_contains($audit, 'backup.created'), 'Packaged audit log missing events.');
    [$storagePage] = request($base . '/admin?view=storage', 'GET', [], $cookie);
    requireCheck(str_contains($storagePage, 'JSON') && str_contains($storagePage, 'الترقية إلى SQLite'), 'Storage management is unavailable.');
    preg_match('/name="csrf" value="([a-f0-9]{64})"/', $storagePage, $token);
    [$upgraded] = request($base . '/admin', 'POST', ['csrf' => $token[1], 'action' => 'storage_upgrade'], $cookie);
    requireCheck(str_contains($upgraded, 'اكتملت الترقية إلى SQLite'), 'SQLite migration failed.');
    $local = require $root . '/config/local.php';
    requireCheck(($local['storage'] ?? null) === 'sqlite' && is_file($private . '/documents.sqlite'), 'SQLite was not activated atomically.');
    [$afterUpgrade, $headers] = request($base . '/p/installed-page');
    requireCheck(str_contains(implode("\n", $headers), '200 OK') && str_contains($afterUpgrade, 'Created inside packaged CMS'), 'Content was not preserved after SQLite migration.');
    [$storagePage] = request($base . '/admin?view=storage', 'GET', [], $cookie);
    requireCheck(str_contains($storagePage, 'SQLITE') && str_contains($storagePage, 'المخزن النشط'), 'SQLite status is not visible.');
} finally {
    proc_terminate($server);
    proc_close($server);
}

$export = $work . '/static-export';
$process = proc_open([PHP_BINARY, $root . '/bin/one', 'export', $private, $export], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
requireCheck(is_resource($process), 'Cannot run packaged exporter.');
fclose($pipes[0]);
$exportOutput = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
requireCheck(proc_close($process) === 0 && is_file($export . '/p/installed-page/index.html'), 'Packaged export failed: ' . $exportOutput);
echo "Packaged CMS browser install, login, publish, SQLite migration and export passed.\n";

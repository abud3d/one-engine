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

function request(string $url, string $method = 'GET', array $data = [], string $cookie = ''): array
{
    $headers = "Host: attacker.invalid\r\n";
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
$private = $work . '/private-data';
requireCheck(!is_dir($root . '/private') && !is_file($root . '/config/local.php'), 'Archive contains private state.');
$password = 'acceptance password 2026';
$command = [PHP_BINARY, $root . '/bin/one-install', $private, 'admin.cms', $password];
$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
requireCheck(is_resource($process), 'Cannot run installer.');
fclose($pipes[0]);
$installOutput = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
requireCheck(proc_close($process) === 0, 'Installer failed: ' . $installOutput);
requireCheck(is_file($root . '/config/local.php') && is_dir($private), 'Installer did not create local state.');
requireCheck((fileperms($root . '/config/local.php') & 0777) === 0600, 'Local config permissions are unsafe.');
requireCheck((fileperms($private) & 0777) === 0700, 'Private directory permissions are unsafe.');

$socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
requireCheck(is_resource($socket), $errorMessage);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$server = proc_open([PHP_BINARY, '-c', $root . '/config/php.ini', '-S', $address, '-t', $root . '/public', $root . '/public/router.php'],
    [0 => ['pipe', 'r'], 1 => ['file', $work . '/server.log', 'a'], 2 => ['file', $work . '/server.log', 'a']], $serverPipes, $root);
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
    [$login, $headers] = request($base . '/admin');
    requireCheck(preg_match('/name="csrf" value="([a-f0-9]{64})"/', $login, $token) === 1, 'Login CSRF missing.');
    requireCheck(preg_match('/Set-Cookie:\s*(ONESESSID=[^;]+)/i', implode("\n", $headers), $cookieMatch) === 1, 'Session cookie missing.');
    $cookie = $cookieMatch[1];
    [, $headers] = request($base . '/admin', 'POST', ['csrf' => $token[1], 'action' => 'login', 'username' => 'admin.cms', 'password' => $password], $cookie);
    requireCheck(str_contains(implode("\n", $headers), '303 See Other'), 'Packaged admin login failed.');
    if (preg_match('/Set-Cookie:\s*(ONESESSID=[^;]+)/i', implode("\n", $headers), $rotated) === 1) {
        $cookie = $rotated[1];
    }
    [$dashboard] = request($base . '/admin', 'GET', [], $cookie);
    requireCheck(preg_match('/name="csrf" value="([a-f0-9]{64})"/', $dashboard, $token) === 1, 'Dashboard CSRF missing.');
    request($base . '/admin', 'POST', ['csrf' => $token[1], 'action' => 'section', 'id' => 'acceptance', 'title' => 'Acceptance', 'description' => '', 'layout' => 'one', 'visibility' => 'public', 'state' => 'published'], $cookie);
    [$dashboard] = request($base . '/admin', 'GET', [], $cookie);
    preg_match('/name="csrf" value="([a-f0-9]{64})"/', $dashboard, $token);
    $page = ['csrf' => $token[1], 'action' => 'page', 'id' => 'installed-page', 'title' => 'Installed page', 'description' => 'Acceptance', 'body' => 'Created inside packaged CMS', 'section_id' => 'acceptance', 'blocks' => '[{"type":"heading","content":"Real block","visibility":"public","column":1}]', 'allowed_users' => '', 'allowed_editors' => '', 'layout' => 'two', 'visibility' => 'public', 'state' => 'draft'];
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
    [$public, $headers] = request($base . '/p/installed-page');
    requireCheck(str_contains(implode("\n", $headers), '200 OK') && str_contains($public, 'Created inside packaged CMS'), 'Published page unavailable.');
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
echo "Packaged CMS install, login, draft preview, publish and export passed.\n";

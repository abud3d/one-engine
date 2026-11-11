<?php
declare(strict_types=1);

/** Real server smoke tests; artifacts live outside the source tree and need no central process. */
function withServer(string $root, callable $checkServer, bool $router = true): void
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    check(is_resource($socket), $errorMessage);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $command = [PHP_BINARY, '-S', $address, '-t', $root . '/public'];
    if ($router) {
        $command[] = $root . '/public/router.php';
    }
    $log = tempnam(sys_get_temp_dir(), 'one-http-');
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root);
    check(is_resource($process));
    fclose($pipes[0]);
    try {
        $ready = false;
        for ($i = 0; $i < 100; $i++) {
            // Startup connection refusal is expected and bounded.
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
        check($ready, 'Server did not start: ' . file_get_contents($log));
        $checkServer('http://' . $address);
    } finally {
        proc_terminate($process);
        proc_close($process);
        unlink($log);
    }
}

function httpRequest(string $url, string $method = 'GET'): array
{
    $context = stream_context_create(['http' => [
        'method' => $method, 'ignore_errors' => true, 'timeout' => 3,
        'header' => "Host: attacker.invalid\r\n",
    ]]);
    $body = file_get_contents($url, false, $context);
    check($body !== false, 'HTTP request failed.');
    return [$body, implode("\n", $http_response_header)];
}

test('HTTP source entrypoint returns safe headers, 404, 405 and HEAD', function (): void {
    withServer(dirname(__DIR__), function (string $base): void {
        [$body, $headers] = httpRequest($base . '/');
        check(str_contains($headers, '200 OK'));
        check(str_contains($headers, 'Content-Security-Policy:'));
        check(str_contains($headers, 'Cache-Control: no-store'));
        check(!str_contains($headers, 'Set-Cookie:'));
        check(!str_contains($body, 'attacker.invalid'));
        check(str_contains($body, 'https://example.invalid/'));
        [$body, $headers] = httpRequest($base . '/config/site.php');
        check(str_contains($headers, '404 Not Found'));
        check(!str_contains($body, 'base_url'));
        [$body, $headers] = httpRequest($base . '/', 'POST');
        check(str_contains($headers, '405 Method Not Allowed'));
        check(str_contains($headers, 'Allow: GET, HEAD'));
        [$body, $headers] = httpRequest($base . '/', 'HEAD');
        check($body === '' && str_contains($headers, '200 OK'));
        [$body, $headers] = httpRequest($base . '/health');
        check(json_decode($body, true) === ['status' => 'ok']);
    });
});
foreach (\OneEngine\Modules\Runtime\Mode::cases() as $mode) {
    test("HTTP $mode->value artifact runs independently of Builder and Central Management", function () use ($temp, $mode): void {
        $root = $temp . '/release-' . $mode->value;
        withServer($root, function (string $base) use ($mode): void {
            [$body, $headers] = httpRequest($base . '/');
            check(str_contains($headers, '200 OK'));
            check(str_contains($body, 'Demo site'));
            [$css, $headers] = httpRequest($base . '/assets/app.css');
            check(str_contains($headers, '200 OK') && str_contains($css, ':root'));
            if ($mode !== \OneEngine\Modules\Runtime\Mode::Static) {
                [$body, $headers] = httpRequest($base . '/health');
                check(json_decode($body, true) === ['status' => 'ok']);
            }
        }, $mode !== \OneEngine\Modules\Runtime\Mode::Static);
    });
}

test('mounted ARBNT pages, assets and administration UI are served without exposing PHP sources', function (): void {
    withServer(dirname(__DIR__), function (string $base): void {
        foreach (['/community/community.html' => 'النقاشات',
                  '/community/login.html' => 'تسجيل',
                  '/community/register.html' => 'إنشاء حساب',
                  '/community/admin.html' => 'إدارة'] as $uri => $text) {
            [$body, $headers] = httpRequest($base . $uri);
            check(str_contains($headers, '200 OK'), 'Missing legacy view: ' . $uri);
            check(str_contains($body, $text), 'Empty legacy view: ' . $uri);
            check(str_contains($headers, 'X-Robots-Tag: noindex'), 'Preview must stay noindex');
        }
        [$css, $headers] = httpRequest($base . '/community/assets/shell.css');
        check(str_contains($headers, '200 OK') && str_contains($css, '.arb-shell'));
        [$js, $headers] = httpRequest($base . '/community/assets/shell.js');
        check(str_contains($headers, '200 OK') && str_contains($js, 'ARBNT'));
        foreach (['/community/app/core.php','/community/install.php','/community/.arbnt-storage.php','/community/api/index.php'] as $uri) {
            [$body, $headers] = httpRequest($base . $uri);
            check(str_contains($headers, '404 Not Found'), 'Private legacy path exposed: ' . $uri);
        }
        [$body, $headers] = httpRequest($base . '/community/api/community.php');
        check(str_contains($headers, '403 Forbidden'), 'Plain HTTP legacy API must reject requests');
        check(!str_contains($body, 'function arbnt_data_dir'));
    });
});

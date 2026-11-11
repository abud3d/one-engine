<?php
declare(strict_types=1);

/** Live API smoke test in an entirely separate temporary directory.
 * Never reads or changes the owner's actual ARBNT data.
 */
test('imported ARBNT API supports real session, login and admin access in isolated fixture', function () use ($temp): void {
    $root = $temp . '/isolated-arbnt';
    foreach (['public/community/api', 'src/LegacyArbnt', 'arbnt-private'] as $folder) {
        mkdir($root . '/' . $folder, 0700, true);
    }
    copy(dirname(__DIR__) . '/public/router.php', $root . '/public/router.php');
    copy(dirname(__DIR__) . '/public/community/api/community.php', $root . '/public/community/api/community.php');
    copy(dirname(__DIR__) . '/src/LegacyArbnt/core.php', $root . '/src/LegacyArbnt/core.php');
    $password = 'Correct-Pass-123456';
    $user = ['id' => 'integration-admin', 'username' => 'testadmin', 'email' => 'admin@example.invalid',
        'verified' => true, 'role' => 'admin', 'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'session_version' => 0];
    foreach (['users' => [$user], 'config' => ['site_name' => 'Test community',
        'site_description' => 'Isolated fixture'], 'posts' => [], 'comments' => [],
        'communities' => [], 'favorites' => [], 'reports' => [], 'notifications' => []] as $name => $records) {
        $file = $root . '/arbnt-private/' . $name . '.json';
        file_put_contents($file, json_encode($records, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        chmod($file, 0600);
    }
    file_put_contents($root . '/arbnt-private/installed.lock', 'test');
    chmod($root . '/arbnt-private/installed.lock', 0600);

    $old = getenv('ONE_CODESPACES_PREVIEW');
    $oldCodespace = getenv('CODESPACE_NAME');
    putenv('ONE_CODESPACES_PREVIEW=1');
    putenv('CODESPACE_NAME=demo');
    try {
        withServer($root, function (string $base) use ($password): void {
            $request = static function (string $method, string $action, ?string $cookie = null, ?string $csrf = null, ?array $payload = null) use ($base): array {
                $headers = "Host: demo-8080.app.github.dev\r\nX-Forwarded-Proto: https\r\n";
                if ($cookie !== null) {
                    $headers .= "Cookie: " . $cookie . "\r\n";
                }
                if ($csrf !== null) {
                    $headers .= "X-CSRF-Token: " . $csrf . "\r\n";
                }
                if ($payload !== null) {
                    $headers .= "Content-Type: application/json\r\n";
                }
                $context = stream_context_create(['http' => ['method' => $method,
                    'header' => $headers, 'ignore_errors' => true, 'timeout' => 4,
                    'content' => $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR)]]);
                $body = file_get_contents($base . '/community/api/community.php?action=' . $action, false, $context);
                check(is_string($body), 'Legacy API did not respond');
                return [json_decode($body, true, flags: JSON_THROW_ON_ERROR), implode("\n", $http_response_header)];
            };
            $probe = static function (string $host, string $proto) use ($base): string {
                $header = "Host: " . $host . "\r\n";
                if ($proto !== '') $header .= "X-Forwarded-Proto: " . $proto . "\r\n";
                $ctx = stream_context_create(['http' => ['method' => 'GET',
                    'header' => $header, 'ignore_errors' => true, 'timeout' => 4]]);
                file_get_contents($base . '/community/api/community.php?action=session', false, $ctx);
                return implode("\n", $http_response_header);
            };
            check(str_contains($probe('attacker-8080.app.github.dev', 'https'), '403 Forbidden'), 'Foreign host accepted');
            check(str_contains($probe('demo-8080.app.github.dev', 'http'), '403 Forbidden'), 'Insecure forwarding accepted');
            check(str_contains($probe('demo-8080.app.github.dev', ''), '200 OK'), 'Codespaces gateway with no XFP rejected');
            [$session, $headers] = $request('GET', 'session');
            check(str_contains($headers, '200 OK'), 'Could not initialize legacy session');
            check($session['user'] === null && is_string($session['csrf']) && strlen($session['csrf']) > 30);
            check(preg_match('/Set-Cookie: PHPSESSID=([^;]+)/i', $headers, $match) === 1, 'Missing session cookie');
            check(str_contains($headers, 'secure') && str_contains($headers, 'HttpOnly'), 'Insecure session cookie');
            $cookie = 'PHPSESSID=' . $match[1];
            [$denied, $deniedHeaders] = $request('GET', 'admin-stats', $cookie);
            check(str_contains($deniedHeaders, '401 Unauthorized'), 'Guests reached admin stats');
            [$login, $loginHeaders] = $request('POST', 'login', $cookie, $session['csrf'],
                ['username' => 'testadmin', 'password' => $password]);
            check(str_contains($loginHeaders, '200 OK') && $login['user']['role'] === 'admin', 'Admin login failed');
            if (preg_match('/Set-Cookie: PHPSESSID=([^;]+)/i', $loginHeaders, $newMatch) === 1) {
                $cookie = 'PHPSESSID=' . $newMatch[1];
            }
            [$stats, $adminHeaders] = $request('GET', 'admin-stats', $cookie);
            check(str_contains($adminHeaders, '200 OK') && $stats['users'] === 1, 'Real admin stats failed');
        });
    } finally {
        if ($oldCodespace === false) {
            putenv('CODESPACE_NAME');
        } else {
            putenv('CODESPACE_NAME=' . $oldCodespace);
        }
        if ($old === false) {
            putenv('ONE_CODESPACES_PREVIEW');
        } else {
            putenv('ONE_CODESPACES_PREVIEW=' . $old);
        }
    }
});

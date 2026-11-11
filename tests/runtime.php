<?php
declare(strict_types=1);

use OneEngine\Modules\Runtime\{Application, Mode, Renderer, Site};
use OneEngine\Modules\Builder\ReleaseBuilder;
use OneEngine\Modules\CentralManagement\SiteCatalog;

function demoSite(Mode $mode = Mode::Dynamic, bool $indexable = false): Site
{
    return new Site('demo', 'Demo site', 'Synthetic description', 'https://example.invalid', $mode, $indexable);
}

test('site URLs reject credentials, non-HTTPS and injected metadata paths', function (): void {
    foreach (['http://example.invalid', 'javascript:alert(1)', 'https://a:b@example.invalid', 'https://example.invalid/path', 'https://example.invalid?x=1', "https://example.invalid\r\nX: bad"] as $url) {
        rejects(fn () => new Site('demo', 'Test', '', $url), InvalidArgumentException::class);
    }
});
test('renderer escapes content and metadata and refuses private or foreign pages', function (): void {
    $renderer = new Renderer();
    $site = new Site('site-a', '<script>alert(1)</script>', '" onload="bad', 'https://example.invalid');
    $html = $renderer->home($site, Application::demoPage($site));
    check(!str_contains($html, '<script>'));
    check(str_contains($html, '&lt;script&gt;'));
    check(str_contains($html, '&quot; onload=&quot;bad'));
    check(str_contains($html, 'noindex,nofollow'));
    rejects(fn () => $renderer->home($site, objectFor()), DomainException::class);
    rejects(fn () => $renderer->home($site, objectFor('other', state: 'published', visibility: 'public')), DomainException::class);
});
test('SEO is per-site and independent of request Host', function (): void {
    $renderer = new Renderer();
    $site = demoSite(indexable: true);
    $other = new Site('other', 'Other', 'Other description', 'https://other.invalid', indexable: true);
    check(str_contains($renderer->sitemap($site), 'https://example.invalid/'));
    check(!str_contains($renderer->sitemap($other), 'example.invalid'));
    check(str_contains($renderer->robots($other), 'https://other.invalid/sitemap.xml'));
    check(!str_contains($renderer->sitemap(demoSite()), '<url>'));
});
test('runtime is read-only with exact routes and security headers', function (): void {
    $app = new Application(demoSite(), dirname(__DIR__) . '/public');
    foreach (['/config/site.php', '/bootstrap.php', '/var/data.json', '/../NOTICE.md', '/assets/../index.php', '/admin', '/%2e%2e/README.md'] as $uri) {
        check($app->handle('GET', $uri)->status === 404);
    }
    check($app->handle('POST', '/')->status === 405);
    check($app->handle('GET', '/health')->body === '{"status":"ok"}');
    $response = $app->handle('GET', '/', true);
    check($response->headers['Cache-Control'] === 'no-store');
    check(str_contains($response->headers['Content-Security-Policy'], "frame-ancestors 'none'"));
    check(isset($response->headers['Strict-Transport-Security']));
    check(!isset($app->handle('GET', '/')->headers['Strict-Transport-Security']));
    check($app->handle('GET', '/assets/app.css')->headers['Content-Type'] === 'text/css; charset=utf-8');
});
test('catalog rejects duplicate site IDs without mutating existing entry', function (): void {
    $catalog = new SiteCatalog();
    $catalog->register(demoSite());
    rejects(fn () => $catalog->register(demoSite()), DomainException::class);
    check(count($catalog->all()) === 1);
});
foreach (Mode::cases() as $mode) {
    test("$mode->value: build contains only standalone allowlisted runtime files", function () use ($temp, $mode): void {
        $destination = $temp . '/release-' . $mode->value;
        $builder = new ReleaseBuilder(dirname(__DIR__));
        $builder->build(demoSite($mode), $destination);
        $manifest = json_decode(file_get_contents($destination . '/release.json'), true, flags: JSON_THROW_ON_ERROR);
        check($manifest['central_required'] === false);
        check($manifest['mode'] === $mode->value);
        check(is_file($destination . '/public/index.html') === ($mode !== Mode::Dynamic));
        check(is_file($destination . '/public/index.php') === ($mode !== Mode::Static));
        foreach ($manifest['files'] as $path => $hash) {
            check(hash_file('sha256', $destination . '/' . $path) === $hash);
            check(!str_contains($path, 'CentralManagement') && !str_contains($path, 'Builder') && !str_contains($path, 'Storage'));
            check(!str_starts_with($path, 'var/') && !str_starts_with($path, '.git'));
            if ($mode === Mode::Static) {
                check(!str_ends_with($path, '.php'));
            }
        }
        rejects(fn () => $builder->build(demoSite($mode), $destination), InvalidArgumentException::class);
        check(json_decode(file_get_contents($destination . '/release.json'), true)['mode'] === $mode->value);
    });
}

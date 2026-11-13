<?php
declare(strict_types=1);

use OneEngine\Core\Action;
use OneEngine\Modules\Content\SiteContent;
use OneEngine\Modules\Identity\{Access, IdentityService};
use OneEngine\Modules\Runtime\{Application, Site};
use OneEngine\Storage\JsonStore;
use OneEngine\Modules\Builder\ContentReleaseBuilder;

test('identity is site scoped and roles deny by default', function () use ($temp): void {
    $private = $temp . '/cms-identity';
    mkdir($private, 0700);
    $identity = new IdentityService(new JsonStore($private, dirname(__DIR__) . '/public'));
    $identity->create('demo', 'admin-1', 'admin.demo', 'correct horse battery', 'admin');
    $user = $identity->authenticate('demo', 'admin.demo', 'correct horse battery');
    check($user !== null && $user['role'] === 'admin');
    check($identity->authenticate('other', 'admin.demo', 'correct horse battery') === null);
    check($identity->authenticate('demo', 'admin.demo', 'wrong password value') === null);
    check(Access::allows($user, Action::Publish));
    check(!Access::allows(['id' => 'member-1', 'role' => 'member'], Action::Update));
    check(!Access::allows(null, Action::Read, ['state' => 'draft', 'visibility' => 'public']));
    for ($i = 0; $i < 5; $i++) {
        check($identity->authenticate('demo', 'missing.user', 'wrong password value') === null);
    }
    rejects(fn () => $identity->authenticate('demo', 'missing.user', 'wrong password value'), DomainException::class);
});

test('reviewed ARBNT accounts reuse password hashes without importing banned or unverified users', function () use ($temp): void {
    $private = $temp . '/arbnt-identity';
    mkdir($private, 0700);
    $identity = new IdentityService(new JsonStore($private, dirname(__DIR__) . '/public'));
    $hash = password_hash('legacy password value', PASSWORD_DEFAULT);
    $count = $identity->importArbnt('demo', [
        ['id' => 'legacy-user', 'username' => 'legacy.user', 'password_hash' => $hash, 'role' => 'member', 'verified' => true],
        ['id' => 'banned-user', 'username' => 'banned.user', 'password_hash' => $hash, 'role' => 'member', 'banned' => true],
        ['id' => 'pending-user', 'username' => 'pending.user', 'password_hash' => $hash, 'role' => 'member', 'verified' => false],
    ]);
    check($count === 1);
    check($identity->authenticate('demo', 'legacy.user', 'legacy password value')['id'] === 'legacy-user');
    check($identity->authenticate('demo', 'banned.user', 'legacy password value') === null);
});

test('sections pages blocks and menus persist with isolation and visibility', function () use ($temp): void {
    $private = $temp . '/cms-content';
    mkdir($private, 0700);
    $content = new SiteContent(new JsonStore($private, dirname(__DIR__) . '/public'));
    $admin = ['id' => 'admin-1', 'role' => 'admin'];
    $editor = ['id' => 'editor-1', 'role' => 'editor'];
    $member = ['id' => 'member-1', 'role' => 'member'];
    $content->saveSection('demo', $admin, ['id' => 'news', 'title' => 'الأخبار', 'description' => '', 'state' => 'published', 'visibility' => 'public', 'layout' => 'one']);
    $content->savePage('demo', $editor, ['id' => 'welcome', 'title' => 'مرحباً', 'description' => 'وصف', 'body' => '<script>unsafe</script>', 'section_id' => 'news', 'state' => 'draft', 'visibility' => 'public', 'layout' => 'two', 'blocks' => [['type' => 'text', 'content' => 'عام', 'visibility' => 'public', 'column' => 1], ['type' => 'text', 'content' => 'للأعضاء', 'visibility' => 'members', 'column' => 2]]]);
    check($content->findPage('demo', 'welcome') === null);
    $content->savePage('demo', $editor, ['id' => 'welcome', 'title' => 'مرحباً', 'description' => 'وصف', 'body' => '<script>unsafe</script>', 'section_id' => 'news', 'state' => 'published', 'visibility' => 'members', 'layout' => 'two', 'blocks' => []]);
    check($content->findPage('demo', 'welcome') === null);
    check($content->findPage('demo', 'welcome', $member) !== null);
    check($content->findPage('other', 'welcome', $member) === null);
    rejects(fn () => $content->savePage('demo', $member, ['id' => 'bad', 'title' => 'Bad']), DomainException::class);
    rejects(fn () => $content->saveMenu('demo', $admin, ['id' => 'evil', 'label' => 'X', 'url' => 'https://evil.invalid', 'position' => 'header']), InvalidArgumentException::class);
    $content->savePage('demo', $admin, ['id' => 'assigned', 'title' => 'محدد', 'description' => '', 'body' => '', 'section_id' => '', 'state' => 'published', 'visibility' => 'private', 'layout' => 'one', 'blocks' => [], 'allowed_users' => ['member-1'], 'allowed_editors' => ['editor-1']]);
    check($content->findPage('demo', 'assigned', $member) !== null);
    check($content->findPage('demo', 'assigned', ['id' => 'member-2', 'role' => 'member']) === null);
    rejects(fn () => $content->savePage('demo', ['id' => 'editor-2', 'role' => 'editor'], ['id' => 'assigned', 'title' => 'ممنوع', 'state' => 'draft']), DomainException::class);
    rejects(fn () => $content->delete('demo', $admin, 'sections', 'news'), DomainException::class);
    $content->delete('demo', $admin, 'cms_pages', 'assigned');
    check($content->findPage('demo', 'assigned', $member) === null);
    rejects(fn () => $content->delete('demo', $editor, 'cms_pages', 'welcome'), DomainException::class);
});

test('templates and media library drive page layout without JSON editing', function () use ($temp): void {
    $private = $temp . '/cms-design';
    mkdir($private, 0700);
    $content = new SiteContent(new JsonStore($private, dirname(__DIR__) . '/public'));
    $admin = ['id' => 'admin-1', 'role' => 'admin'];
    $content->saveTemplate('demo', $admin, ['id' => 'feature', 'title' => 'قالب مميز', 'description' => '', 'layout' => 'three', 'sidebar' => '1']);
    $content->saveMedia('demo', $admin, ['id' => 'hero', 'title' => 'الصورة الرئيسية', 'alt' => 'وصف الصورة', 'url' => 'https://cdn.example.test/hero.webp']);
    $content->savePage('demo', $admin, ['id' => 'designed', 'title' => 'صفحة مصممة', 'description' => '', 'body' => '', 'section_id' => '', 'template_id' => 'feature', 'state' => 'published', 'visibility' => 'public', 'layout' => 'one', 'blocks' => [['type' => 'image', 'content' => 'https://cdn.example.test/hero.webp', 'visibility' => 'public', 'column' => 2]]]);
    $page = $content->findPage('demo', 'designed');
    check($page !== null && $page['layout'] === 'three' && $page['show_sidebar'] === true);
    rejects(fn () => $content->delete('demo', $admin, 'templates', 'feature'), DomainException::class);
    rejects(fn () => $content->delete('demo', $admin, 'media', 'hero'), DomainException::class);
    rejects(fn () => $content->saveMedia('demo', $admin, ['id' => 'unsafe', 'title' => 'Unsafe', 'url' => 'http://example.test/a.png']), InvalidArgumentException::class);
});

test('identity administration changes roles and preserves a final active administrator', function () use ($temp): void {
    $private = $temp . '/cms-user-admin';
    mkdir($private, 0700);
    $identity = new IdentityService(new JsonStore($private, dirname(__DIR__) . '/public'));
    $identity->create('demo', 'admin-one', 'admin.one', 'administrator password', 'admin');
    $identity->create('demo', 'user-one', 'user.one', 'member password value', 'member');
    $identity->update('demo', 'user-one', 'editor', true, 'new editor password');
    check($identity->authenticate('demo', 'user.one', 'new editor password')['role'] === 'editor');
    $sessionActor = $identity->authenticate('demo', 'user.one', 'new editor password');
    $identity->update('demo', 'user-one', 'member', true);
    check($identity->validateSession('demo', $sessionActor) === null);
    rejects(fn () => $identity->update('demo', 'admin-one', 'member', true), DomainException::class);
});

test('public CMS route escapes content and hides drafts private blocks and foreign sites', function () use ($temp): void {
    $private = $temp . '/cms-runtime';
    mkdir($private, 0700);
    $content = new SiteContent(new JsonStore($private, dirname(__DIR__) . '/public'));
    $admin = ['id' => 'admin-1', 'role' => 'admin'];
    $base = ['description' => 'SEO', 'section_id' => '', 'state' => 'published', 'visibility' => 'public', 'layout' => 'one'];
    $content->savePage('demo', $admin, array_merge($base, ['id' => 'safe', 'title' => '<script>Title</script>', 'body' => '<img src=x>', 'blocks' => [['type' => 'text', 'content' => '<b>escaped</b>', 'visibility' => 'public', 'column' => 1], ['type' => 'text', 'content' => 'hidden secret', 'visibility' => 'private', 'column' => 1]]]));
    $content->savePage('demo', $admin, array_merge($base, ['id' => 'draft', 'title' => 'Draft', 'body' => 'secret', 'state' => 'draft', 'blocks' => []]));
    $content->savePage('other', $admin, array_merge($base, ['id' => 'foreign', 'title' => 'Foreign', 'body' => 'foreign secret', 'blocks' => []]));
    $content->saveMenu('demo', $admin, ['id' => 'home-link', 'label' => 'الرئيسية', 'url' => '/', 'position' => 'header', 'visibility' => 'public']);
    $app = new Application(demoSite(), dirname(__DIR__) . '/public', null, null, $content);
    $page = $app->handle('GET', '/p/safe');
    check($page->status === 200 && str_contains($page->body, '&lt;script&gt;'));
    check(!str_contains($page->body, '<script>') && !str_contains($page->body, 'hidden secret'));
    check(str_contains($page->body, 'الرئيسية'));
    check($app->handle('GET', '/p/draft')->status === 404);
    check($app->handle('GET', '/p/foreign')->status === 404);
    check($app->handle('GET', '/sections/news')->status === 200);
    $editorApp = new Application(demoSite(), dirname(__DIR__) . '/public', null, null, $content, ['id' => 'editor-1', 'role' => 'editor']);
    check($editorApp->handle('GET', '/preview/draft')->status === 200);
    check($app->handle('GET', '/preview/draft')->status === 404);
});

test('content export includes only public published pages with independent SEO', function () use ($temp): void {
    $private = $temp . '/cms-export-data';
    mkdir($private, 0700);
    $content = new SiteContent(new JsonStore($private, dirname(__DIR__) . '/public'));
    $admin = ['id' => 'admin-1', 'role' => 'admin'];
    $page = ['title' => 'عام', 'description' => 'وصف', 'body' => 'محتوى', 'section_id' => '', 'state' => 'published', 'visibility' => 'public', 'layout' => 'one', 'blocks' => []];
    $content->savePage('demo', $admin, ['id' => 'public-page'] + $page);
    $content->savePage('demo', $admin, ['id' => 'private-page', 'visibility' => 'private'] + $page);
    $content->savePage('demo', $admin, ['id' => 'draft-page', 'state' => 'draft'] + $page);
    $content->savePage('other', $admin, ['id' => 'foreign-page'] + $page);
    $site = new Site('demo', 'Demo', 'Description', 'https://demo.example', \OneEngine\Modules\Runtime\Mode::Static, true);
    $destination = $temp . '/cms-export';
    (new ContentReleaseBuilder(dirname(__DIR__), $content))->build($site, $destination);
    check(is_file($destination . '/p/public-page/index.html'));
    check(!is_dir($destination . '/p/private-page') && !is_dir($destination . '/p/draft-page') && !is_dir($destination . '/p/foreign-page'));
    check(str_contains((string) file_get_contents($destination . '/sitemap.xml'), '/p/public-page'));
    check(!str_contains((string) file_get_contents($destination . '/sitemap.xml'), 'private-page'));
    check(str_contains((string) file_get_contents($destination . '/p/public-page/index.html'), 'href="../../assets/app.css"'));
    $manifest = json_decode((string) file_get_contents($destination . '/release.json'), true, 32, JSON_THROW_ON_ERROR);
    check($manifest['public_pages'] === 1 && $manifest['central_required'] === false);
});

test('administrator browser flow logs in creates publishes and exposes only public page', function () use ($temp): void {
    $private = $temp . '/cms-browser';
    mkdir($private, 0700);
    $store = new JsonStore($private, dirname(__DIR__) . '/public');
    (new IdentityService($store))->create('one-demo', 'admin-browser', 'browser.admin', 'browser acceptance pass', 'admin');
    withServer(dirname(__DIR__), function (string $base): void {
        $request = static function (string $url, string $method = 'GET', array $data = [], string $cookie = ''): array {
            $headers = "Host: attacker.invalid\r\n";
            if ($cookie !== '') {
                $headers .= 'Cookie: ' . $cookie . "\r\n";
            }
            if ($method === 'POST') {
                $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
            }
            $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'header' => $headers, 'content' => $method === 'POST' ? http_build_query($data) : '']]);
            $body = file_get_contents($url, false, $context);
            check(is_string($body));
            return [$body, $http_response_header];
        };
        [$login, $headers] = $request($base . '/admin');
        check(preg_match('/name="csrf" value="([a-f0-9]{64})"/', $login, $match) === 1);
        $joined = implode("\n", $headers);
        check(preg_match('/Set-Cookie:\s*(ONESESSID=[^;]+)/i', $joined, $cookieMatch) === 1);
        $cookie = $cookieMatch[1];
        [, $headers] = $request($base . '/admin', 'POST', ['csrf' => $match[1], 'action' => 'login', 'username' => 'browser.admin', 'password' => 'browser acceptance pass'], $cookie);
        check(str_contains(implode("\n", $headers), '303 See Other'));
        // Rotation sends a replacement cookie.
        if (preg_match('/Set-Cookie:\s*(ONESESSID=[^;]+)/i', implode("\n", $headers), $rotated) === 1) {
            $cookie = $rotated[1];
        }
        [$dashboard] = $request($base . '/admin', 'GET', [], $cookie);
        check(str_contains($dashboard, \OneEngine\Core\EngineVersion::current()->label()));
        check(str_contains($dashboard, 'ONE-030-001') && str_contains($dashboard, 'JSON'));
        check(str_contains($dashboard, 'لوحة الموقع') && preg_match('/name="csrf" value="([a-f0-9]{64})"/', $dashboard, $token) === 1);
        [$editor, $headers] = $request($base . '/admin?view=pages', 'GET', [], $cookie);
        check(str_contains($editor, 'name="block_type[]"') && !str_contains($editor, 'name="blocks"'));
        check(str_contains(implode("\n", $headers), "form-action 'self'") && str_contains(implode("\n", $headers), "script-src 'self'"));
        [$script, $headers] = $request($base . '/assets/admin.js');
        check(str_contains(implode("\n", $headers), '200 OK') && str_contains($script, 'data-add-block'));
        [, $headers] = $request($base . '/admin', 'POST', ['csrf' => $token[1], 'action' => 'section', 'id' => 'news', 'title' => 'الأخبار', 'description' => '', 'layout' => 'one', 'visibility' => 'public', 'state' => 'published'], $cookie);
        check(str_contains(implode("\n", $headers), '200 OK'));
        [$dashboard] = $request($base . '/admin', 'GET', [], $cookie);
        preg_match('/name="csrf" value="([a-f0-9]{64})"/', $dashboard, $token);
        $page = ['csrf' => $token[1], 'action' => 'page', 'id' => 'browser-page', 'title' => 'صفحة المتصفح', 'description' => 'وصف', 'body' => 'محتوى فعلي', 'section_id' => 'news', 'block_type' => ['heading'], 'block_content' => ['بلوك العنوان'], 'block_visibility' => ['public'], 'block_column' => ['1'], 'allowed_users' => [], 'allowed_editors' => [], 'layout' => 'two', 'visibility' => 'public', 'state' => 'draft'];
        [$saved, $headers] = $request($base . '/admin', 'POST', $page, $cookie);
        check(str_contains(implode("\n", $headers), '200 OK') && str_contains($saved, 'تم الحفظ'));
        [$preview, $headers] = $request($base . '/preview/browser-page', 'GET', [], $cookie);
        check(str_contains(implode("\n", $headers), '200 OK') && str_contains($preview, 'بلوك العنوان'));
        [, $headers] = $request($base . '/p/browser-page');
        check(str_contains(implode("\n", $headers), '404 Not Found'));
        $page['state'] = 'published';
        [$dashboard] = $request($base . '/admin', 'GET', [], $cookie);
        preg_match('/name="csrf" value="([a-f0-9]{64})"/', $dashboard, $token);
        $page['csrf'] = $token[1];
        $request($base . '/admin', 'POST', $page, $cookie);
        [$dashboard] = $request($base . '/admin', 'GET', [], $cookie);
        preg_match('/name="csrf" value="([a-f0-9]{64})"/', $dashboard, $token);
        $request($base . '/admin', 'POST', ['csrf' => $token[1], 'action' => 'menu', 'id' => 'browser-link', 'label' => 'الرابط التجريبي', 'url' => '/p/browser-page', 'position' => 'header', 'visibility' => 'public'], $cookie);
        [$public, $headers] = $request($base . '/p/browser-page');
        check(str_contains(implode("\n", $headers), '200 OK') && str_contains($public, 'محتوى فعلي') && str_contains($public, 'الرابط التجريبي'));
        [$section, $headers] = $request($base . '/sections/news');
        check(str_contains(implode("\n", $headers), '200 OK') && str_contains($section, 'صفحة المتصفح'));
    }, true, ['ONE_PRIVATE_DIR' => $private]);
    $export = $temp . '/browser-flow-export';
    $site = new Site('one-demo', 'Demo', 'Description', 'https://demo.example', \OneEngine\Modules\Runtime\Mode::Static, true);
    (new ContentReleaseBuilder(dirname(__DIR__), new SiteContent($store)))->build($site, $export);
    check(is_file($export . '/p/browser-page/index.html') && str_contains((string) file_get_contents($export . '/p/browser-page/index.html'), 'بلوك العنوان'));
});

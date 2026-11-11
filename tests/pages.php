<?php
declare(strict_types=1);

use OneEngine\Modules\Runtime\{Application, PageCatalog, CommunityCatalog};
use OneEngine\Storage\JsonStore;

test('page catalog publishes only explicitly public pages, keeps drafts private and escapes HTML', function () use ($temp): void {
    $private = $temp . '/pages-private';
    mkdir($private, 0700);
    $catalog = new PageCatalog(new JsonStore($private, dirname(__DIR__) . '/public'));
    $catalog->save('demo', 'first', 'أول صفحة', 'هذا محتوى حقيقي', true);
    $catalog->save('demo', 'draft', 'سر', 'Private', false);
    $catalog->save('other', 'foreign', 'موقع آخر', 'Foreign', true);
    $catalog->save('demo', 'html', '<script>alert(1)</script>', '<img src=x onerror=alert(1)>', true);
    check(count($catalog->published('demo')) === 2);
    check(count($catalog->published('other')) === 1);
    $app = new Application(demoSite(), dirname(__DIR__) . '/public', $catalog);
    $index = $app->handle('GET', '/pages');
    check($index->status === 200 && str_contains($index->body, 'أول صفحة'));
    check(str_contains($index->body, 'class="one-nav"'));
    check(str_contains($index->body, 'class="one-sidebar"'));
    check(str_contains($index->body, 'id="one-theme-toggle"'));
    check(str_contains($index->body, '/assets/preview-shell.js'));
    check(str_contains($index->headers['Content-Security-Policy'], "script-src 'self'"));
    check($app->handle('GET', '/assets/preview-shell.js')->status === 200);
    check($app->handle('GET', '/assets/preview-shell.css')->status === 200);
    check($app->handle('GET', '/assets/preview-shell.js')->headers['Content-Type'] === 'text/javascript; charset=utf-8');
    check(!str_contains($index->body, 'Private') && !str_contains($index->body, 'Foreign'));
    check($app->handle('GET', '/pages/draft')->status === 404);
    check($app->handle('GET', '/pages/foreign')->status === 404);
    $html = $app->handle('GET', '/pages/html');
    check($html->status === 200);
    check(!str_contains($html->body, '<script>') && !str_contains($html->body, '<img'));
    check(str_contains($html->body, '&lt;script&gt;'));
    check($app->handle('POST', '/pages')->status === 405);
    check($app->handle('GET', '/pages/../config')->status === 404);
    check((new Application(demoSite(), dirname(__DIR__) . '/public'))->handle('GET', '/assets/preview-shell.js')->status === 200);
    check(str_contains($app->handle('GET', '/')->body, 'href="/pages"'));
    check(str_contains($app->handle('GET', '/')->body, 'href="/community"'));
});

test('page publishing validates slugs and stays isolated per site', function () use ($temp): void {
    $private = $temp . '/pages-private2';
    mkdir($private, 0700);
    $catalog = new PageCatalog(new JsonStore($private, dirname(__DIR__) . '/public'));
    rejects(fn () => $catalog->save('demo', '../secret', 'oops', 'body', true), InvalidArgumentException::class);
    rejects(fn () => $catalog->save('demo', 'UPPER', 'oops', 'body', true), InvalidArgumentException::class);
    $catalog->save('demo', 'note', 'Note', 'Body', false);
    check(count($catalog->published('demo')) === 0);
    $catalog->save('demo', 'note', 'Note', 'Body', true);
    check(count($catalog->published('demo')) === 1);
    $catalog->save('demo', 'note', 'Note', 'Body', false);
    check(count($catalog->published('demo')) === 0);
});

test('community feed and routes work without configured private storage', function (): void {
    $app = new Application(demoSite(), dirname(__DIR__) . '/public');
    check($app->handle('GET', '/pages')->status === 200);
    $feed = $app->handle('GET', '/community');
    check($feed->status === 200);
    check(str_contains($feed->body, 'لا توجد منشورات عامة بعد'));
    check(str_contains($feed->body, 'href="/community"'));
    check(str_contains($app->handle('GET', '/')->body, 'href="/pages"'));
    check(str_contains($app->handle('GET', '/')->body, 'href="/community"'));
    check($app->handle('GET', '/community/example')->status === 404);
    check($app->handle('POST', '/community')->status === 405);
    check($app->handle('GET', '/api/community.php')->status === 404);
});

test('community posts persist per site and hide drafts, escaped HTML and private records', function () use ($temp): void {
    $private = $temp . '/community-private';
    mkdir($private, 0700);
    $community = new CommunityCatalog(new JsonStore($private, dirname(__DIR__) . '/public'));
    $community->save('demo', 'hello', 'مرحباً في المجتمع', 'أول موضوع محفوظ', true);
    $community->save('demo', 'draft', 'مسودة', 'secret text', false);
    $community->save('other', 'foreign', 'خاص بموقع آخر', 'foreign secret', true);
    $community->save('demo', 'html', '<script>alert(1)</script>', '<img src=x onerror=alert(1)>', true);
    check(count($community->published('demo')) === 2);
    $app = new Application(demoSite(), dirname(__DIR__) . '/public', null, $community);
    $feed = $app->handle('GET', '/community');
    check($feed->status === 200 && str_contains($feed->body, 'مرحباً في المجتمع'));
    check(!str_contains($feed->body, 'secret text') && !str_contains($feed->body, 'foreign secret'));
    check($app->handle('GET', '/community/draft')->status === 404);
    check($app->handle('GET', '/community/foreign')->status === 404);
    $post = $app->handle('GET', '/community/html');
    check($post->status === 200 && !str_contains($post->body, '<script>'));
    check(str_contains($post->body, '&lt;script&gt;'));
    check(str_contains($post->headers['Content-Security-Policy'], "script-src 'self'"));
    rejects(fn () => $community->save('demo', '../bad', 'title', 'body', true), InvalidArgumentException::class);
    $community->save('demo', 'hello', 'مرحباً في المجتمع', 'المنشور أصبح مسودة', false);
    check($app->handle('GET', '/community/hello')->status === 404);
});

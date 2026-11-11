<?php
declare(strict_types=1);

use OneEngine\Modules\Runtime\{Application, PageCatalog};
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
    check(!str_contains($index->body, 'Private') && !str_contains($index->body, 'Foreign'));
    check($app->handle('GET', '/pages/draft')->status === 404);
    check($app->handle('GET', '/pages/foreign')->status === 404);
    $html = $app->handle('GET', '/pages/html');
    check($html->status === 200);
    check(!str_contains($html->body, '<script>') && !str_contains($html->body, '<img'));
    check(str_contains($html->body, '&lt;script&gt;'));
    check($app->handle('POST', '/pages')->status === 405);
    check($app->handle('GET', '/pages/../config')->status === 404);
    check(str_contains($app->handle('GET', '/')->body, 'تصفح الصفحات المنشورة'));
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

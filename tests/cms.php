<?php
declare(strict_types=1);

use OneEngine\Core\Action;
use OneEngine\Modules\Content\SiteContent;
use OneEngine\Modules\Identity\{Access, IdentityService};
use OneEngine\Modules\Runtime\{Application, Site};
use OneEngine\Storage\JsonStore;

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
});

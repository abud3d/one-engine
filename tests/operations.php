<?php
declare(strict_types=1);

use OneEngine\Modules\CentralManagement\{AuditLog, BackupService, OperationLock, StorageMigration};
use OneEngine\Modules\Content\SiteContent;
use OneEngine\Security\OriginGuard;
use OneEngine\Storage\{JsonStore, SqliteStore};

test('trusted Origin rejects missing foreign and malformed administration origins', function (): void {
    check(OriginGuard::validate('https://cms.example.test/') === 'https://cms.example.test');
    OriginGuard::requireAllowed('https://cms.example.test', ['HTTP_ORIGIN' => 'https://cms.example.test']);
    rejects(fn () => OriginGuard::requireAllowed('https://cms.example.test', []), DomainException::class);
    rejects(fn () => OriginGuard::requireAllowed('https://cms.example.test', ['HTTP_ORIGIN' => 'https://evil.example']), DomainException::class);
    rejects(fn () => OriginGuard::validate('http://cms.example.test'), InvalidArgumentException::class);
    rejects(fn () => OriginGuard::validate('https://cms.example.test/path'), InvalidArgumentException::class);
});

test('audit log records bounded metadata without content payloads', function () use ($temp): void {
    $private = $temp . '/audit-data';
    mkdir($private, 0700);
    $audit = new AuditLog(new JsonStore($private, dirname(__DIR__) . '/public'));
    $audit->record('demo', 'admin-1', 'content.page', 'home');
    $events = $audit->recent('demo');
    check(count($events) === 1 && $events[0]['actor_id'] === 'admin-1' && $events[0]['target'] === 'home');
    rejects(fn () => $audit->record('demo', 'admin-1', 'Bad action', 'home'), InvalidArgumentException::class);
});

test('site settings are managed data and reject unsafe canonical origins', function () use ($temp): void {
    $private = $temp . '/settings-data';
    mkdir($private, 0700);
    $content = new SiteContent(new JsonStore($private, dirname(__DIR__) . '/public'));
    $admin = ['id' => 'admin-1', 'role' => 'admin'];
    $content->saveSettings('demo', $admin, ['title' => 'موقع حقيقي', 'description' => 'وصف مستقل', 'base_url' => 'https://site.example/', 'indexable' => '1']);
    $settings = $content->settings('demo');
    check($settings['title'] === 'موقع حقيقي' && $settings['base_url'] === 'https://site.example' && $settings['indexable'] === true);
    rejects(fn () => $content->saveSettings('demo', $admin, ['title' => 'Bad', 'base_url' => 'http://site.example']), InvalidArgumentException::class);
    rejects(fn () => $content->saveSettings('demo', ['id' => 'editor-1', 'role' => 'editor'], ['title' => 'Bad', 'base_url' => 'https://site.example']), DomainException::class);
});

test('backup restores validated site documents and corrupt backup never overwrites data', function () use ($temp): void {
    $private = $temp . '/backup-data';
    mkdir($private, 0700);
    $store = new JsonStore($private, dirname(__DIR__) . '/public');
    $content = new SiteContent($store);
    $admin = ['id' => 'admin-1', 'role' => 'admin'];
    $content->savePage('demo', $admin, ['id' => 'home', 'title' => 'Original', 'description' => '', 'body' => 'Original body', 'section_id' => '', 'state' => 'published', 'visibility' => 'public', 'layout' => 'one', 'blocks' => []]);
    (new AuditLog($store))->record('demo', 'admin-1', 'content.page', 'home');
    $lock = new OperationLock($private, 100);
    $backups = new BackupService($store, $private, $lock);
    $name = $backups->create('demo');
    check(count($backups->all()) === 1 && is_file($private . '/backups/' . $name));
    $content->savePage('demo', $admin, ['id' => 'home', 'title' => 'Changed', 'description' => '', 'body' => 'Changed body', 'section_id' => '', 'state' => 'published', 'visibility' => 'public', 'layout' => 'one', 'blocks' => []]);
    $backups->restore('demo', $name);
    check($content->findPage('demo', 'home')['title'] === 'Original');

    $valid = (string) file_get_contents($private . '/backups/' . $name);
    file_put_contents($private . '/backups/' . $name, '{broken');
    rejects(fn () => $backups->restore('demo', $name), JsonException::class);
    check($content->findPage('demo', 'home')['title'] === 'Original');
    file_put_contents($private . '/backups/' . $name, $valid);
    $tampered = json_decode($valid, true, 64, JSON_THROW_ON_ERROR);
    $tampered['documents']['cms_pages'][0]['title'] = 'Tampered';
    file_put_contents($private . '/backups/' . $name, json_encode($tampered, JSON_THROW_ON_ERROR));
    rejects(fn () => $backups->restore('demo', $name), UnexpectedValueException::class);
    check($content->findPage('demo', 'home')['title'] === 'Original');
});

test('operation lock has a bounded timeout during restore contention', function () use ($temp): void {
    $private = $temp . '/operation-lock';
    mkdir($private, 0700);
    $held = fopen($private . '/.one-operation.lock', 'c+b');
    check(is_resource($held) && flock($held, LOCK_EX));
    try {
        rejects(fn () => (new OperationLock($private, 10))->exclusive(static fn (): bool => true), RuntimeException::class);
    } finally {
        flock($held, LOCK_UN);
        fclose($held);
    }
});

test('JSON to SQLite migration verifies documents and switches configuration atomically', function () use ($temp): void {
    $root = $temp . '/migration-root';
    $private = $temp . '/migration-private';
    mkdir($root . '/config', 0700, true);
    mkdir($root . '/public', 0700);
    mkdir($private, 0700);
    file_put_contents($root . '/config/local.php', "<?php return ['private_dir' => " . var_export($private, true) . ", 'trusted_origin' => 'https://example.test', 'storage' => 'json'];");
    chmod($root . '/config/local.php', 0600);
    $json = new JsonStore($private, $root . '/public');
    $json->update('demo', 'users', static fn (): array => [['id' => 'admin-1', 'username' => 'admin', 'role' => 'admin', 'active' => true]]);
    $lock = new OperationLock($private, 100);
    $backups = new BackupService($json, $private, $lock);
    (new StorageMigration($root, $private, 'demo', $json, $backups, $lock))->toSqlite();
    $config = require $root . '/config/local.php';
    check($config['storage'] === 'sqlite' && count($backups->all()) === 1);
    check((new SqliteStore($private, $root . '/public'))->read('demo', 'users')[0]['id'] === 'admin-1');
    check($json->read('demo', 'users')[0]['id'] === 'admin-1');
});

test('failed SQLite migration leaves JSON active and untouched', function () use ($temp): void {
    $root = $temp . '/migration-fail-root';
    $private = $temp . '/migration-fail-private';
    mkdir($root . '/config', 0700, true);
    mkdir($root . '/public', 0700);
    mkdir($private, 0700);
    file_put_contents($root . '/config/local.php', "<?php return ['private_dir' => " . var_export($private, true) . ", 'trusted_origin' => 'https://example.test', 'storage' => 'json'];");
    chmod($root . '/config/local.php', 0600);
    $json = new JsonStore($private, $root . '/public');
    $json->update('demo', 'users', static fn (): array => [['id' => 'keep']]);
    file_put_contents($private . '/4-demo-settings.json', '{broken');
    $lock = new OperationLock($private, 100);
    $backups = new BackupService($json, $private, $lock);
    rejects(fn () => (new StorageMigration($root, $private, 'demo', $json, $backups, $lock))->toSqlite(), JsonException::class);
    check((require $root . '/config/local.php')['storage'] === 'json');
    check($json->read('demo', 'users')[0]['id'] === 'keep');
    check(file_get_contents($private . '/4-demo-settings.json') === '{broken');
});

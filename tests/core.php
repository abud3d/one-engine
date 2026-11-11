<?php
declare(strict_types=1);

use OneEngine\Core\{Action, ContentObject, Grant, Identifier, PermissionEngine, Principal, Relation};
use OneEngine\Security\Csrf;

function objectFor(string $site = 'site-a', string $id = 'page-a', string $state = 'draft', string $visibility = 'private'): ContentObject
{
    return new ContentObject($site, $id, 'page', 'alice', 'Title', 'Body', $state, $visibility, Action::cases());
}

test('identifiers reject traversal, separators and control characters', function (): void {
    foreach (['../users', 'a/b', 'a.b', '', "a\n", 'a:b', str_repeat('a', 65)] as $id) {
        rejects(fn () => Identifier::check($id), InvalidArgumentException::class);
    }
});
test('objects validate state, capability and UTF-8', function (): void {
    rejects(fn () => objectFor(state: 'unknown'), InvalidArgumentException::class);
    rejects(fn () => new ContentObject('s', 'p', 'page', 'a', "\xff", ''), InvalidArgumentException::class);
    rejects(fn () => new ContentObject('s', 'p', 'page', 'a', '', '', capabilities: ['admin']), InvalidArgumentException::class);
});
test('relations reject cross-site and self references', function (): void {
    $a = objectFor();
    $b = objectFor(id: 'page-b');
    check((new Relation($a, $b, 'reply-to'))->siteId === 'site-a');
    rejects(fn () => new Relation($a, objectFor('site-b'), 'reply-to'), InvalidArgumentException::class);
    rejects(fn () => new Relation($a, $a, 'reply-to'), InvalidArgumentException::class);
});
test('default deny includes owner without grants and anonymous private reads', function (): void {
    $policy = new PermissionEngine();
    foreach (Action::cases() as $action) {
        check(!$policy->allows(new Principal('alice'), $action, objectFor(), 'site-a'));
        check(!$policy->allows(new Principal(), $action, objectFor(), 'site-a'));
    }
});
test('public reads require published state and read capability', function (): void {
    $policy = new PermissionEngine();
    foreach (['draft', 'published', 'archived'] as $state) {
        check($policy->allows(new Principal(), Action::Read, objectFor(state: $state, visibility: 'public'), 'site-a') === ($state === 'published'));
    }
    $object = new ContentObject('site-a', 'p', 'page', 'alice', '', '', 'published', 'public', []);
    check(!$policy->allows(new Principal(), Action::Read, $object, 'site-a'));
});
test('grants enforce actor, action, object and site scope', function (): void {
    $policy = new PermissionEngine();
    $grant = new Grant('site-a', 'alice', Action::Update, 'page-a');
    check($policy->allows(new Principal('alice'), Action::Update, objectFor(), 'site-a', [$grant]));
    check(!$policy->allows(new Principal('bob'), Action::Update, objectFor(), 'site-a', [$grant]));
    check(!$policy->allows(new Principal('alice'), Action::Delete, objectFor(), 'site-a', [$grant]));
    check(!$policy->allows(new Principal('alice'), Action::Update, objectFor(id: 'page-b'), 'site-a', [$grant]));
    check(!$policy->allows(new Principal('alice'), Action::Update, objectFor(), 'site-b', [$grant]));
    check(!$policy->allows(new Principal('alice'), Action::Update, objectFor('site-b'), 'site-b', [$grant]));
});
test('explicit deny beats allow in both orders, including public read', function (): void {
    $policy = new PermissionEngine();
    $allow = new Grant('site-a', 'alice', Action::Read);
    $deny = new Grant('site-a', 'alice', Action::Read, 'page-a', false);
    foreach ([[$allow, $deny], [$deny, $allow]] as $grants) {
        check(!$policy->allows(new Principal('alice'), Action::Read, objectFor(state: 'published', visibility: 'public'), 'site-a', $grants));
    }
});
test('inactive actor and disabled capability cannot be overridden by grant', function (): void {
    $policy = new PermissionEngine();
    $grant = new Grant('site-a', 'alice', Action::Update);
    check(!$policy->allows(new Principal('alice', false), Action::Update, objectFor(), 'site-a', [$grant]));
    $object = new ContentObject('site-a', 'p', 'page', 'alice', '', '');
    check(!$policy->allows(new Principal('alice'), Action::Update, $object, 'site-a', [$grant]));
});
test('CSRF tokens reject empty, mismatched and malformed values', function (): void {
    $token = Csrf::issue();
    check(Csrf::valid($token, $token));
    check(!Csrf::valid('', ''));
    check(!Csrf::valid($token, Csrf::issue()));
    check(!Csrf::valid('wrong', 'wrong'));
});

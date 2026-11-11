<?php
declare(strict_types=1);

namespace OneEngine\Modules\Identity;

use OneEngine\Core\Action;

final class Access
{
    /** @param array{id:string,role:string}|null $actor */
    public static function allows(?array $actor, Action $action, ?array $record = null): bool
    {
        if ($action === Action::Read && ($record['state'] ?? null) === 'published' && ($record['visibility'] ?? null) === 'public') {
            return true;
        }
        if ($actor === null) {
            return false;
        }
        $role = $actor['role'] ?? '';
        if ($role === 'admin') {
            return true;
        }
        if ($role === 'editor') {
            return in_array($action, [Action::Read, Action::Create, Action::Update, Action::Publish], true);
        }
        if ($role === 'member' && $action === Action::Read) {
            return ($record['state'] ?? null) === 'published'
                && in_array(($record['visibility'] ?? null), ['public', 'members'], true);
        }
        return false;
    }
}

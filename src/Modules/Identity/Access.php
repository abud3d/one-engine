<?php
declare(strict_types=1);

namespace OneEngine\Modules\Identity;

use OneEngine\Core\{Action, ContentObject, Grant, PermissionEngine, Principal};

final class Access
{
    /** @param array{id:string,role:string}|null $actor */
    public static function allows(?array $actor, Action $action, ?array $record = null, string $siteId = 'site'): bool
    {
        $candidateState = $record['state'] ?? 'draft';
        $state = in_array($candidateState, ['draft', 'published', 'archived'], true) ? $candidateState : 'draft';
        $recordVisibility = $record['visibility'] ?? 'private';
        $visibility = $recordVisibility === 'public' ? 'public' : 'private';
        $objectId = is_string($record['id'] ?? null) ? $record['id'] : 'site-policy';
        $object = new ContentObject($siteId, $objectId, 'managed-content', 'system', '', '', $state, $visibility, Action::cases());
        $principal = new Principal(is_string($actor['id'] ?? null) ? $actor['id'] : null);
        $grants = [];
        if ($principal->id !== null) {
            $role = $actor['role'] ?? '';
            $editors = is_array($record['allowed_editors'] ?? null) ? $record['allowed_editors'] : [];
            $readers = is_array($record['allowed_users'] ?? null) ? $record['allowed_users'] : [];
            $editorAllows = $role === 'editor' && (
                in_array($action, [Action::Read, Action::Create], true)
                || (in_array($action, [Action::Update, Action::Publish], true) && ($record === null || $editors === [] || in_array($principal->id, $editors, true)))
            );
            $memberAllows = $role === 'member' && $action === Action::Read && $state === 'published'
                && ($recordVisibility === 'members' || in_array($principal->id, $readers, true));
            $roleAllows = $role === 'admin' || $editorAllows || $memberAllows;
            if ($roleAllows) {
                $grants[] = new Grant($siteId, $principal->id, $action);
            }
        }
        return (new PermissionEngine())->allows($principal, $action, $object, $siteId, $grants);
    }
}

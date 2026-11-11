<?php
declare(strict_types=1);

namespace OneEngine\Core;

final class PermissionEngine
{
    /** Grants must be loaded by trusted application code, never from a request body.
     * @param list<Grant> $grants
     */
    public function allows(Principal $actor, Action $action, ContentObject $object, string $siteId, array $grants = []): bool
    {
        if (!$actor->active || $siteId !== $object->siteId || !in_array($action, $object->capabilities, true)) {
            return false;
        }
        $allowed = false;
        foreach ($grants as $grant) {
            if (!$grant instanceof Grant) {
                return false;
            }
            if ($grant->siteId === $siteId && $grant->principalId === $actor->id
                && $grant->action === $action && ($grant->objectId === null || $grant->objectId === $object->id)) {
                if (!$grant->allow) {
                    return false;
                }
                $allowed = true;
            }
        }
        if ($action === Action::Read && $object->state === 'published' && $object->visibility === 'public') {
            return true;
        }
        return $actor->id !== null && $allowed;
    }
}

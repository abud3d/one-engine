<?php
declare(strict_types=1);

namespace OneEngine\Modules\CentralManagement;

use OneEngine\Core\Identifier;
use OneEngine\Storage\DocumentStore;

final readonly class AuditLog
{
    public function __construct(private DocumentStore $store, private ?OperationLock $lock = null) {}

    public function record(string $siteId, string $actorId, string $action, string $target): void
    {
        Identifier::check($siteId);
        Identifier::check($actorId);
        if (!preg_match('/\A[a-z][a-z0-9_.-]{1,63}\z/D', $action) || strlen($target) > 160 || !preg_match('//u', $target)) {
            throw new \InvalidArgumentException('Invalid audit event.');
        }
        $work = function () use ($siteId, $actorId, $action, $target): void {
            $this->store->update($siteId, 'audit', static function (array $events) use ($actorId, $action, $target): array {
                if (!array_is_list($events)) throw new \UnexpectedValueException('Invalid audit log.');
                $events[] = ['id' => bin2hex(random_bytes(12)), 'actor_id' => $actorId, 'action' => $action, 'target' => $target, 'created_at' => gmdate('c')];
                return count($events) > 2000 ? array_slice($events, -2000) : $events;
            });
        };
        if ($this->lock === null) $work(); else $this->lock->shared($work);
    }

    /** @return list<array<string,mixed>> */
    public function recent(string $siteId, int $limit = 100): array
    {
        $events = $this->store->read($siteId, 'audit');
        if (!array_is_list($events)) throw new \UnexpectedValueException('Invalid audit log.');
        return array_reverse(array_slice($events, -max(1, min($limit, 500))));
    }
}

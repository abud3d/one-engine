<?php
declare(strict_types=1);

namespace OneEngine\Core;

final readonly class Grant
{
    public function __construct(
        public string $siteId,
        public string $principalId,
        public Action $action,
        public ?string $objectId = null,
        public bool $allow = true,
    ) {
        Identifier::check($siteId);
        Identifier::check($principalId);
        if ($objectId !== null) {
            Identifier::check($objectId);
        }
    }
}

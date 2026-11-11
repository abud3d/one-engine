<?php
declare(strict_types=1);

namespace OneEngine\Core;

final readonly class ContentObject
{
    /** @param list<Action> $capabilities */
    public function __construct(
        public string $siteId,
        public string $id,
        public string $type,
        public string $ownerId,
        public string $title,
        public string $body,
        public string $state = 'draft',
        public string $visibility = 'private',
        public array $capabilities = [Action::Read],
    ) {
        foreach ([$siteId, $id, $type, $ownerId] as $identifier) {
            Identifier::check($identifier);
        }
        if (!in_array($state, ['draft', 'published', 'archived'], true)
            || !in_array($visibility, ['public', 'private'], true)) {
            throw new \InvalidArgumentException('Invalid object state or visibility.');
        }
        foreach ($capabilities as $capability) {
            if (!$capability instanceof Action) {
                throw new \InvalidArgumentException('Unknown capability.');
            }
        }
        foreach ([$title, $body] as $text) {
            if (!preg_match('//u', $text)) {
                throw new \InvalidArgumentException('Text must be UTF-8.');
            }
        }
        if (strlen($title) > 500 || strlen($body) > 100000) {
            throw new \InvalidArgumentException('Object exceeds text limits.');
        }
    }
}

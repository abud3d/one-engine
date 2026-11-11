<?php
declare(strict_types=1);

namespace OneEngine\Core;

final readonly class Relation
{
    public string $siteId;
    public string $sourceId;
    public string $targetId;

    public function __construct(ContentObject $source, ContentObject $target, public string $type)
    {
        Identifier::check($type);
        if ($source->siteId !== $target->siteId || $source->id === $target->id) {
            throw new \InvalidArgumentException('Relation requires distinct objects in the same site.');
        }
        $this->siteId = $source->siteId;
        $this->sourceId = $source->id;
        $this->targetId = $target->id;
    }
}

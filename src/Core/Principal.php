<?php
declare(strict_types=1);

namespace OneEngine\Core;

final readonly class Principal
{
    public function __construct(public ?string $id = null, public bool $active = true)
    {
        if ($id !== null) {
            Identifier::check($id);
        }
    }
}

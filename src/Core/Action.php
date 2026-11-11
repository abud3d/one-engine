<?php
declare(strict_types=1);

namespace OneEngine\Core;

enum Action: string
{
    case Read = 'read';
    case Create = 'create';
    case Update = 'update';
    case Publish = 'publish';
    case Delete = 'delete';
    case Comment = 'comment';
}

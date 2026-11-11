<?php
declare(strict_types=1);

namespace OneEngine\Core;

enum Action: string
{
    case Read = 'read';
    case Update = 'update';
    case Delete = 'delete';
    case Comment = 'comment';
}

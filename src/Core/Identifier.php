<?php
declare(strict_types=1);

namespace OneEngine\Core;

final class Identifier
{
    public static function check(string $value): string
    {
        if (!preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}\z/', $value)) {
            throw new \InvalidArgumentException('Invalid identifier.');
        }
        return $value;
    }
}

<?php
declare(strict_types=1);

namespace OneEngine\Security;

/** Primitive for future authenticated modules; the foundation exposes no write routes. */
final class Csrf
{
    public static function issue(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function valid(string $expected, string $provided): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', $expected) === 1 && hash_equals($expected, $provided);
    }
}

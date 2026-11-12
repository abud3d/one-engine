<?php
declare(strict_types=1);

namespace OneEngine\Security;

final class OriginGuard
{
    public static function requireAllowed(?string $trustedOrigin, array $server): void
    {
        if ($trustedOrigin === null) {
            return; // Explicit development mode; packaged installs always configure an origin.
        }
        $origin = $server['HTTP_ORIGIN'] ?? null;
        if (!is_string($origin) || !hash_equals($trustedOrigin, rtrim($origin, '/'))) {
            throw new \DomainException('Request origin is not allowed.');
        }
    }

    public static function validate(string $origin): string
    {
        $origin = rtrim($origin, '/');
        $parts = parse_url($origin);
        if (!filter_var($origin, FILTER_VALIDATE_URL) || !is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new \InvalidArgumentException('Trusted origin must be one HTTPS origin without a path.');
        }
        return $origin;
    }
}

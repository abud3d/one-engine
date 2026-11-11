<?php
declare(strict_types=1);

namespace OneEngine\Modules\Identity;

use OneEngine\Security\Csrf;

final class SessionGuard
{
    public static function start(bool $https): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('ONESESSID');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
        if (!session_start(['use_strict_mode' => true, 'use_only_cookies' => true])) {
            throw new \RuntimeException('Session unavailable.');
        }
        $_SESSION['csrf'] ??= Csrf::issue();
    }

    public static function login(array $user, string $siteId): void
    {
        session_regenerate_id(true);
        $_SESSION['actor'] = $user;
        $_SESSION['site_id'] = $siteId;
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf'] = Csrf::issue();
    }

    public static function actor(string $siteId): ?array
    {
        $actor = $_SESSION['actor'] ?? null;
        $last = $_SESSION['last_activity'] ?? 0;
        if (!is_int($last) || $last < time() - 1800) {
            unset($_SESSION['actor'], $_SESSION['site_id'], $_SESSION['last_activity']);
            return null;
        }
        if (($_SESSION['site_id'] ?? null) !== $siteId || !is_array($actor)) {
            return null;
        }
        $_SESSION['last_activity'] = time();
        return $actor;
    }

    public static function csrf(): string
    {
        return is_string($_SESSION['csrf'] ?? null) ? $_SESSION['csrf'] : '';
    }

    public static function requireCsrf(string $provided): void
    {
        if (!Csrf::valid(self::csrf(), $provided)) {
            throw new \DomainException('Invalid CSRF token.');
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf'] = Csrf::issue();
    }
}

<?php
declare(strict_types=1);

namespace OneEngine\Modules\Identity;

final class LoginThrottle
{
    public static function check(): void
    {
        $attempts = $_SESSION['login_attempts'] ?? [];
        $attempts = array_values(array_filter(is_array($attempts) ? $attempts : [], static fn ($time): bool => is_int($time) && $time > time() - 600));
        $_SESSION['login_attempts'] = $attempts;
        if (count($attempts) >= 5) {
            throw new \DomainException('Too many login attempts.');
        }
    }

    public static function failure(): void
    {
        self::check();
        $_SESSION['login_attempts'][] = time();
    }

    public static function success(): void
    {
        unset($_SESSION['login_attempts']);
    }
}

<?php
declare(strict_types=1);

namespace OneEngine\Modules\Identity;

use OneEngine\Core\Identifier;
use OneEngine\Storage\DocumentStore;

final readonly class IdentityService
{
    private const ROLES = ['admin', 'editor', 'member'];

    public function __construct(private DocumentStore $store)
    {
    }

    public function create(string $siteId, string $id, string $username, string $password, string $role): void
    {
        Identifier::check($siteId);
        Identifier::check($id);
        self::validateUsername($username);
        self::validatePassword($password);
        if (!in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException('Invalid role.');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new \RuntimeException('Password hashing failed.');
        }
        $this->store->update($siteId, 'users', static function (array $users) use ($id, $username, $hash, $role): array {
            if (!array_is_list($users)) {
                throw new \UnexpectedValueException('Invalid user index.');
            }
            foreach ($users as $user) {
                if (!is_array($user) || ($user['id'] ?? null) === $id || ($user['username'] ?? null) === $username) {
                    throw new \DomainException('User already exists.');
                }
            }
            if (count($users) >= 100) {
                throw new \LengthException('User limit reached.');
            }
            $users[] = ['id' => $id, 'username' => $username, 'password_hash' => $hash, 'role' => $role, 'active' => true, 'session_version' => 0];
            return $users;
        });
    }

    public function update(string $siteId, string $id, string $role, bool $active, string $password = ''): void
    {
        Identifier::check($siteId);
        Identifier::check($id);
        if (!in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException('Invalid role.');
        }
        $hash = null;
        if ($password !== '') {
            self::validatePassword($password);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if (!is_string($hash)) {
                throw new \RuntimeException('Password hashing failed.');
            }
        }
        $this->store->update($siteId, 'users', static function (array $users) use ($id, $role, $active, $hash): array {
            if (!array_is_list($users)) {
                throw new \UnexpectedValueException('Invalid user index.');
            }
            $found = false;
            $activeAdmins = 0;
            foreach ($users as $user) {
                if (is_array($user) && ($user['role'] ?? null) === 'admin' && ($user['active'] ?? false) === true) {
                    $activeAdmins++;
                }
            }
            foreach ($users as &$user) {
                if (!is_array($user) || ($user['id'] ?? null) !== $id) {
                    continue;
                }
                if (($user['role'] ?? null) === 'admin' && ($user['active'] ?? false) === true
                    && ($role !== 'admin' || !$active) && $activeAdmins <= 1) {
                    throw new \DomainException('Cannot disable the final administrator.');
                }
                $user['role'] = $role;
                $user['active'] = $active;
                if ($hash !== null) {
                    $user['password_hash'] = $hash;
                }
                $user['session_version'] = (int) ($user['session_version'] ?? 0) + 1;
                $found = true;
                break;
            }
            unset($user);
            if (!$found) {
                throw new \DomainException('User not found.');
            }
            return $users;
        });
    }

    /** @return array<string,list<string>> */
    public static function roleCapabilities(): array
    {
        return [
            'admin' => ['read', 'create', 'update', 'publish', 'delete', 'manage_users'],
            'editor' => ['read', 'create', 'update', 'publish'],
            'member' => ['read_assigned'],
        ];
    }

    /** Import a reviewed, offline ARBNT users export. Never reads production storage itself. */
    public function importArbnt(string $siteId, array $rows): int
    {
        Identifier::check($siteId);
        if (!array_is_list($rows) || count($rows) > 500) {
            throw new \InvalidArgumentException('Invalid ARBNT user export.');
        }
        $accepted = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !empty($row['banned']) || (isset($row['verified']) && $row['verified'] !== true)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            $username = strtolower((string) ($row['username'] ?? ''));
            $hash = (string) ($row['password_hash'] ?? '');
            $role = in_array(($row['role'] ?? ''), self::ROLES, true) ? $row['role'] : 'member';
            Identifier::check($id);
            self::validateUsername($username);
            if (password_get_info($hash)['algo'] === null) {
                throw new \InvalidArgumentException('Invalid password hash in ARBNT export.');
            }
            $accepted[] = ['id' => $id, 'username' => $username, 'password_hash' => $hash, 'role' => $role, 'active' => true, 'source' => 'arbnt'];
        }
        $this->store->update($siteId, 'users', static function (array $users) use ($accepted): array {
            if (!array_is_list($users)) {
                throw new \UnexpectedValueException('Invalid user index.');
            }
            $ids = array_column($users, null, 'id');
            $names = array_column($users, null, 'username');
            foreach ($accepted as $user) {
                if (isset($ids[$user['id']]) || isset($names[$user['username']])) {
                    throw new \DomainException('ARBNT user conflicts with an existing account.');
                }
                $users[] = $user;
            }
            return $users;
        });
        return count($accepted);
    }

    /** @return array{id:string,username:string,role:string}|null */
    public function authenticate(string $siteId, string $username, string $password): ?array
    {
        Identifier::check($siteId);
        self::validateUsername($username);
        $attemptKey = hash('sha256', $username);
        $attempts = $this->store->update($siteId, 'login_attempts', static function (array $all) use ($attemptKey): array {
            $now = time();
            foreach ($all as $key => $times) {
                if (!is_array($times)) {
                    throw new \UnexpectedValueException('Invalid login attempt record.');
                }
                $all[$key] = array_values(array_filter($times, static fn ($time): bool => is_int($time) && $time > $now - 600));
                if ($all[$key] === []) {
                    unset($all[$key]);
                }
            }
            if (count($all[$attemptKey] ?? []) >= 5) {
                throw new \DomainException('Too many login attempts.');
            }
            if (count($all) >= 500 && !isset($all[$attemptKey])) {
                throw new \DomainException('Login service is temporarily limited.');
            }
            $all[$attemptKey][] = $now;
            return $all;
        });
        foreach ($this->store->read($siteId, 'users') as $user) {
            if (!is_array($user) || !isset($user['id'], $user['username'], $user['password_hash'], $user['role'], $user['active'])) {
                throw new \UnexpectedValueException('Invalid user record.');
            }
            if ($user['username'] === $username && $user['active'] === true && password_verify($password, (string) $user['password_hash'])) {
                $this->store->update($siteId, 'login_attempts', static function (array $all) use ($attemptKey): array {
                    unset($all[$attemptKey]);
                    return $all;
                });
                return ['id' => (string) $user['id'], 'username' => $username, 'role' => (string) $user['role'], 'session_version' => (int) ($user['session_version'] ?? 0)];
            }
        }
        password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
        return null;
    }

    /** @return list<array{id:string,username:string,role:string,active:bool}> */
    public function all(string $siteId): array
    {
        $result = [];
        foreach ($this->store->read($siteId, 'users') as $user) {
            if (!is_array($user) || !isset($user['id'], $user['username'], $user['role'], $user['active'])) {
                throw new \UnexpectedValueException('Invalid user record.');
            }
            $result[] = ['id' => (string) $user['id'], 'username' => (string) $user['username'], 'role' => (string) $user['role'], 'active' => (bool) $user['active']];
        }
        return $result;
    }

    public function validateSession(string $siteId, array $actor): ?array
    {
        foreach ($this->store->read($siteId, 'users') as $user) {
            if (!is_array($user) || ($user['id'] ?? null) !== ($actor['id'] ?? null)) continue;
            if (($user['active'] ?? false) !== true || (int) ($user['session_version'] ?? 0) !== (int) ($actor['session_version'] ?? 0)) return null;
            return ['id' => (string) $user['id'], 'username' => (string) $user['username'], 'role' => (string) $user['role'], 'session_version' => (int) ($user['session_version'] ?? 0)];
        }
        return null;
    }

    private static function validateUsername(string $username): void
    {
        if (!preg_match('/\A[a-z0-9][a-z0-9_.-]{2,31}\z/D', $username)) {
            throw new \InvalidArgumentException('Invalid username.');
        }
    }

    private static function validatePassword(string $password): void
    {
        if (strlen($password) < 12 || strlen($password) > 256) {
            throw new \InvalidArgumentException('Password must be 12-256 bytes.');
        }
    }
}

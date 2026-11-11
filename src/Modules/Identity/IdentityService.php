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
            $users[] = ['id' => $id, 'username' => $username, 'password_hash' => $hash, 'role' => $role, 'active' => true];
            return $users;
        });
    }

    /** @return array{id:string,username:string,role:string}|null */
    public function authenticate(string $siteId, string $username, string $password): ?array
    {
        Identifier::check($siteId);
        self::validateUsername($username);
        foreach ($this->store->read($siteId, 'users') as $user) {
            if (!is_array($user) || !isset($user['id'], $user['username'], $user['password_hash'], $user['role'], $user['active'])) {
                throw new \UnexpectedValueException('Invalid user record.');
            }
            if ($user['username'] === $username && $user['active'] === true && password_verify($password, (string) $user['password_hash'])) {
                return ['id' => (string) $user['id'], 'username' => $username, 'role' => (string) $user['role']];
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

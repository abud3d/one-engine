<?php
declare(strict_types=1);

namespace OneEngine\Storage;

final class StoreFactory
{
    public static function create(string $type, string $privateDirectory, string $publicRoot): DocumentStore
    {
        return match ($type) {
            'json' => new JsonStore($privateDirectory, $publicRoot),
            'sqlite' => new SqliteStore($privateDirectory, $publicRoot),
            default => throw new \UnexpectedValueException('Unsupported storage type.'),
        };
    }
}

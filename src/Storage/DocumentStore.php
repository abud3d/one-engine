<?php
declare(strict_types=1);

namespace OneEngine\Storage;

interface DocumentStore
{
    /** Missing documents return []; corrupt documents throw. */
    public function read(string $siteId, string $key): array;

    /** Atomically read/modify/write ONE document. Callback must be pure and non-reentrant.
     * @param callable(array): array $change
     */
    public function update(string $siteId, string $key, callable $change): array;
}

<?php
declare(strict_types=1);

namespace OneEngine\Storage;

use OneEngine\Core\Identifier;

final class SqliteStore implements DocumentStore
{
    private \PDO $pdo;

    public function __construct(string $directory, string $publicRoot)
    {
        if (!extension_loaded('pdo_sqlite')) throw new \RuntimeException('PDO_SQLite is not available.');
        $directory = PrivateDirectory::resolve($directory, $publicRoot);
        $path = $directory . '/documents.sqlite';
        foreach ([$path, $path . '-journal', $path . '-wal', $path . '-shm'] as $file) {
            if (is_link($file) || (file_exists($file) && !is_file($file))) throw new \RuntimeException('Unsafe SQLite file.');
        }
        $this->pdo = new \PDO('sqlite:' . $path, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $this->pdo->exec('PRAGMA journal_mode=WAL; PRAGMA synchronous=FULL; PRAGMA busy_timeout=5000; CREATE TABLE IF NOT EXISTS documents (site_id TEXT NOT NULL, document_key TEXT NOT NULL, payload TEXT NOT NULL, PRIMARY KEY(site_id, document_key))');
        if (!chmod($path, 0600)) throw new \RuntimeException('Cannot protect SQLite storage.');
    }

    public function read(string $siteId, string $key): array
    {
        $this->validate($siteId, $key);
        $statement = $this->pdo->prepare('SELECT payload FROM documents WHERE site_id = :site AND document_key = :key');
        $statement->execute(['site' => $siteId, 'key' => $key]);
        $json = $statement->fetchColumn();
        if ($json === false) return [];
        $data = json_decode((string) $json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new \UnexpectedValueException('Invalid SQLite document.');
        return $data;
    }

    public function update(string $siteId, string $key, callable $change): array
    {
        $this->validate($siteId, $key);
        $this->pdo->exec('BEGIN IMMEDIATE');
        try {
            $next = $change($this->read($siteId, $key));
            if (!is_array($next)) throw new \UnexpectedValueException('Document must be an array.');
            $json = json_encode($next, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE, 64);
            if (strlen($json) > 2097152) throw new \LengthException('Document is too large.');
            $statement = $this->pdo->prepare('INSERT INTO documents(site_id, document_key, payload) VALUES(:site,:key,:payload) ON CONFLICT(site_id,document_key) DO UPDATE SET payload=excluded.payload');
            $statement->execute(['site' => $siteId, 'key' => $key, 'payload' => $json]);
            $this->pdo->commit();
            return $next;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    private function validate(string $siteId, string $key): void
    {
        Identifier::check($siteId);
        Identifier::check($key);
    }
}

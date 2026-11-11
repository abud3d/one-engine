<?php
declare(strict_types=1);

namespace OneEngine\Storage;

use OneEngine\Core\Identifier;

final class SqliteStore implements DocumentStore
{
    private \PDO $db;

    public function __construct(string $directory, string $publicRoot)
    {
        $path = PrivateDirectory::resolve($directory, $publicRoot) . '/documents.sqlite';
        foreach ([$path, $path . '-journal', $path . '-wal', $path . '-shm'] as $file) {
            if (is_link($file) || (file_exists($file) && !is_file($file))) {
                throw new \RuntimeException('Unsafe database path.');
            }
        }
        $this->db = new \PDO('sqlite:' . $path, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        if (!chmod($path, 0600)) {
            throw new \RuntimeException('Cannot protect database.');
        }
        $this->db->exec('PRAGMA busy_timeout = 1000');
        $this->db->exec('CREATE TABLE IF NOT EXISTS documents (site_id TEXT NOT NULL, document_key TEXT NOT NULL, payload TEXT NOT NULL, PRIMARY KEY (site_id, document_key))');
    }

    public function read(string $siteId, string $key): array
    {
        Identifier::check($siteId);
        Identifier::check($key);
        $query = $this->db->prepare('SELECT payload FROM documents WHERE site_id = ? AND document_key = ?');
        $query->execute([$siteId, $key]);
        $json = $query->fetchColumn();
        if ($json === false) {
            return [];
        }
        $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Invalid document shape.');
        }
        return $data;
    }

    public function update(string $siteId, string $key, callable $change): array
    {
        Identifier::check($siteId);
        Identifier::check($key);
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $next = $change($this->read($siteId, $key));
            if (!is_array($next)) {
                throw new \UnexpectedValueException('Document must be an array.');
            }
            $json = json_encode($next, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE, 64);
            if (strlen($json) > 2097152) {
                throw new \LengthException('Document is too large.');
            }
            $query = $this->db->prepare('INSERT INTO documents VALUES (?, ?, ?) ON CONFLICT(site_id, document_key) DO UPDATE SET payload = excluded.payload');
            $query->execute([$siteId, $key, $json]);
            $this->db->exec('COMMIT');
            return $next;
        } catch (\Throwable $error) {
            $this->db->exec('ROLLBACK');
            throw $error;
        }
    }
}

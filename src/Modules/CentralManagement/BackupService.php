<?php
declare(strict_types=1);

namespace OneEngine\Modules\CentralManagement;

use OneEngine\Core\Identifier;
use OneEngine\Storage\DocumentStore;

final readonly class BackupService
{
    private const KEYS = ['users', 'sections', 'cms_pages', 'menus', 'templates', 'media', 'settings', 'audit'];
    private string $directory;

    public function __construct(private DocumentStore $store, string $privateDirectory, private OperationLock $lock)
    {
        $root = realpath($privateDirectory);
        if ($root === false || !is_dir($root)) throw new \InvalidArgumentException('Invalid backup root.');
        $this->directory = $root . '/backups';
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700)) throw new \RuntimeException('Cannot create backup directory.');
        if (is_link($this->directory) || !chmod($this->directory, 0700)) throw new \RuntimeException('Cannot protect backup directory.');
    }

    public function create(string $siteId): string
    {
        Identifier::check($siteId);
        return $this->lock->exclusive(function () use ($siteId): string {
            $documents = [];
            foreach (self::KEYS as $key) $documents[$key] = $this->store->read($siteId, $key);
            $base = ['schema' => 1, 'site_id' => $siteId, 'created_at' => gmdate('c'), 'documents' => $documents];
            $base['checksum'] = hash('sha256', json_encode($base, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $json = json_encode($base, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $name = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.json';
            $path = $this->directory . '/' . $name;
            $temp = $path . '.tmp';
            $handle = fopen($temp, 'x+b');
            if ($handle === false) throw new \RuntimeException('Cannot create backup.');
            try {
                if (!chmod($temp, 0600) || fwrite($handle, $json) !== strlen($json) || !fflush($handle) || !fsync($handle) || !rename($temp, $path)) throw new \RuntimeException('Cannot commit backup.');
            } finally {
                fclose($handle);
                if (is_file($temp)) unlink($temp);
            }
            return $name;
        });
    }

    public function restore(string $siteId, string $name): void
    {
        Identifier::check($siteId);
        $path = $this->path($name);
        $this->lock->exclusive(function () use ($siteId, $path): void {
            $json = file_get_contents($path);
            if ($json === false || strlen($json) > 8388608) throw new \RuntimeException('Backup is unreadable or too large.');
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($data) || ($data['schema'] ?? null) !== 1 || ($data['site_id'] ?? null) !== $siteId || !is_array($data['documents'] ?? null) || !is_string($data['checksum'] ?? null)) throw new \UnexpectedValueException('Invalid backup.');
            $checksum = $data['checksum'];
            unset($data['checksum']);
            $actual = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if (!hash_equals($checksum, $actual)) throw new \UnexpectedValueException('Backup checksum mismatch.');
            foreach (self::KEYS as $key) if (!isset($data['documents'][$key]) || !is_array($data['documents'][$key])) throw new \UnexpectedValueException('Backup document missing.');
            $before = [];
            foreach (self::KEYS as $key) $before[$key] = $this->store->read($siteId, $key);
            $applied = [];
            try {
                foreach (self::KEYS as $key) {
                    $next = $data['documents'][$key];
                    $this->store->update($siteId, $key, static fn (): array => $next);
                    $applied[] = $key;
                }
            } catch (\Throwable $error) {
                foreach (array_reverse($applied) as $key) {
                    $previous = $before[$key];
                    $this->store->update($siteId, $key, static fn (): array => $previous);
                }
                throw $error;
            }
        });
    }

    /** @return list<array{name:string,size:int,modified:string}> */
    public function all(): array
    {
        $result = [];
        foreach (new \DirectoryIterator($this->directory) as $file) {
            if ($file->isDot() || !$file->isFile() || $file->isLink() || !preg_match('/\A\d{8}-\d{6}-[a-f0-9]{12}\.json\z/D', $file->getFilename())) continue;
            $result[] = ['name' => $file->getFilename(), 'size' => $file->getSize(), 'modified' => gmdate('c', $file->getMTime())];
        }
        usort($result, static fn (array $a, array $b): int => strcmp($b['name'], $a['name']));
        return $result;
    }

    private function path(string $name): string
    {
        if (!preg_match('/\A\d{8}-\d{6}-[a-f0-9]{12}\.json\z/D', $name)) throw new \InvalidArgumentException('Invalid backup name.');
        $path = $this->directory . '/' . $name;
        if (!is_file($path) || is_link($path)) throw new \DomainException('Backup not found.');
        return $path;
    }
}

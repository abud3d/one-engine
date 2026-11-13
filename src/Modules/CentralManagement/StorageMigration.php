<?php
declare(strict_types=1);

namespace OneEngine\Modules\CentralManagement;

use OneEngine\Storage\{DocumentStore, JsonStore, SqliteStore};

final readonly class StorageMigration
{
    private const KEYS = ['users', 'sections', 'cms_pages', 'menus', 'templates', 'media', 'settings', 'audit', 'pages', 'community-posts', 'login_attempts'];

    public function __construct(private string $root, private string $privateDirectory, private string $siteId, private DocumentStore $current, private BackupService $backups, private OperationLock $lock) {}

    public function toSqlite(): void
    {
        if ($this->current instanceof SqliteStore) throw new \DomainException('SQLite is already active.');
        if (!$this->current instanceof JsonStore) throw new \DomainException('Only JSON can be upgraded.');
        $this->backups->create($this->siteId);
        $this->lock->exclusive(function (): void {
            $target = new SqliteStore($this->privateDirectory, $this->root . '/public');
            foreach (self::KEYS as $key) {
                $source = $this->current->read($this->siteId, $key);
                $target->update($this->siteId, $key, static fn (): array => $source);
                if (hash('sha256', serialize($target->read($this->siteId, $key))) !== hash('sha256', serialize($source))) throw new \RuntimeException('SQLite verification failed.');
            }
            $this->writeConfiguration('sqlite');
        });
    }

    private function writeConfiguration(string $storage): void
    {
        $path = $this->root . '/config/local.php';
        if (!is_file($path) || is_link($path)) throw new \RuntimeException('Local configuration is unavailable.');
        $current = require $path;
        if (!is_array($current)) throw new \UnexpectedValueException('Invalid local configuration.');
        $current['storage'] = $storage;
        $content = "<?php\ndeclare(strict_types=1);\nreturn " . var_export($current, true) . ";\n";
        $temp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $handle = fopen($temp, 'x+b');
        if ($handle === false) throw new \RuntimeException('Cannot stage storage switch.');
        try {
            if (!chmod($temp, 0600) || fwrite($handle, $content) !== strlen($content) || !fflush($handle) || !fsync($handle) || !rename($temp, $path)) throw new \RuntimeException('Cannot commit storage switch.');
        } finally {
            fclose($handle);
            if (is_file($temp)) unlink($temp);
        }
    }
}

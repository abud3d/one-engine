<?php
declare(strict_types=1);

namespace OneEngine\Storage;

use OneEngine\Core\Identifier;

/** Local filesystems only. A stable lock protects each document across rename. */
final class JsonStore implements DocumentStore
{
    private string $directory;
    private const MAX_BYTES = 2097152;

    public function __construct(string $directory, string $publicRoot, private int $timeoutMs = 1000)
    {
        if ($timeoutMs < 1 || $timeoutMs > 10000) {
            throw new \InvalidArgumentException('Invalid lock timeout.');
        }
        $this->directory = PrivateDirectory::resolve($directory, $publicRoot);
    }

    public function read(string $siteId, string $key): array
    {
        return $this->locked($siteId, $key, LOCK_SH, fn (string $path): array => $this->decode($path));
    }

    public function update(string $siteId, string $key, callable $change): array
    {
        return $this->locked($siteId, $key, LOCK_EX, function (string $path) use ($change): array {
            $next = $change($this->decode($path));
            if (!is_array($next)) {
                throw new \UnexpectedValueException('Document must be an array.');
            }
            $json = json_encode($next, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE, 64);
            if (strlen($json) > self::MAX_BYTES) {
                throw new \LengthException('Document is too large.');
            }
            $temp = $path . '.' . bin2hex(random_bytes(12)) . '.tmp';
            $stream = fopen($temp, 'x+b');
            if ($stream === false) {
                throw new \RuntimeException('Cannot create temporary document.');
            }
            try {
                if (!chmod($temp, 0600)) {
                    throw new \RuntimeException('Cannot protect document.');
                }
                $offset = 0;
                while ($offset < strlen($json)) {
                    $written = fwrite($stream, substr($json, $offset));
                    if ($written === false || $written === 0) {
                        throw new \RuntimeException('Incomplete write.');
                    }
                    $offset += $written;
                }
                if (!fflush($stream) || !fsync($stream) || !rename($temp, $path)) {
                    throw new \RuntimeException('Cannot commit document.');
                }
            } finally {
                fclose($stream);
                if (is_file($temp)) {
                    unlink($temp);
                }
            }
            return $next;
        });
    }

    private function decode(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Cannot read document.');
        }
        try {
            $json = stream_get_contents($stream, self::MAX_BYTES + 1);
        } finally {
            fclose($stream);
        }
        if ($json === false || strlen($json) > self::MAX_BYTES) {
            throw new \LengthException('Document is too large or unreadable.');
        }
        $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Invalid document shape.');
        }
        return $data;
    }

    private function locked(string $siteId, string $key, int $mode, callable $work): array
    {
        // Length prefixes prevent ambiguous site/key pairs without user paths.
        Identifier::check($siteId);
        Identifier::check($key);
        $base = $this->directory . '/' . strlen($siteId) . '-' . $siteId . '-' . $key;
        foreach ([$base . '.lock', $base . '.json'] as $file) {
            if (is_link($file) || (file_exists($file) && !is_file($file))) {
                throw new \RuntimeException('Unsafe storage file.');
            }
        }
        $lock = fopen($base . '.lock', 'c+b');
        if ($lock === false) {
            throw new \RuntimeException('Cannot open document lock.');
        }
        try {
            if (!chmod($base . '.lock', 0600)) {
                throw new \RuntimeException('Cannot protect lock.');
            }
            $deadline = hrtime(true) + $this->timeoutMs * 1000000;
            while (!flock($lock, $mode | LOCK_NB)) {
                if (hrtime(true) >= $deadline) {
                    throw new \RuntimeException('Storage lock timeout.');
                }
                usleep(1000);
            }
            return $work($base . '.json');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

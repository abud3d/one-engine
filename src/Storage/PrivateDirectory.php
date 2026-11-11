<?php
declare(strict_types=1);

namespace OneEngine\Storage;

final class PrivateDirectory
{
    public static function resolve(string $path, string $publicRoot): string
    {
        $root = realpath($publicRoot);
        $directory = realpath($path);
        if ($root === false || $directory === false || !is_dir($directory) || is_link($path)
            || $directory === $root || str_starts_with($directory, $root . DIRECTORY_SEPARATOR)) {
            throw new \InvalidArgumentException('Storage must exist outside the public root.');
        }
        // Deployment must also exclude this path from aliases and other virtual hosts.
        if (!is_writable($directory) || (fileperms($directory) & 0077) !== 0) {
            throw new \RuntimeException('Storage directory must be writable with mode 0700.');
        }
        return $directory;
    }
}

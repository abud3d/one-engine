<?php
declare(strict_types=1);

namespace OneEngine\Modules\CentralManagement;

final readonly class OperationLock
{
    private string $path;

    public function __construct(string $privateDirectory, private int $timeoutMs = 3000)
    {
        $resolved = realpath($privateDirectory);
        if ($resolved === false || !is_dir($resolved) || ($timeoutMs < 1 || $timeoutMs > 10000)) {
            throw new \InvalidArgumentException('Invalid operation lock configuration.');
        }
        $this->path = $resolved . '/.one-operation.lock';
    }

    public function shared(callable $work): mixed { return $this->run(LOCK_SH, $work); }
    public function exclusive(callable $work): mixed { return $this->run(LOCK_EX, $work); }

    private function run(int $mode, callable $work): mixed
    {
        if (is_link($this->path) || (file_exists($this->path) && !is_file($this->path))) throw new \RuntimeException('Unsafe operation lock.');
        $handle = fopen($this->path, 'c+b');
        if ($handle === false) throw new \RuntimeException('Cannot open operation lock.');
        try {
            if (!chmod($this->path, 0600)) throw new \RuntimeException('Cannot protect operation lock.');
            $deadline = hrtime(true) + $this->timeoutMs * 1000000;
            while (!flock($handle, $mode | LOCK_NB)) {
                if (hrtime(true) >= $deadline) throw new \RuntimeException('Operation lock timeout.');
                usleep(1000);
            }
            return $work();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

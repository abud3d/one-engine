<?php
declare(strict_types=1);

namespace OneEngine\Core;

final readonly class EngineVersion
{
    private function __construct(public string $version, public string $build, public string $builtAt) {}

    public static function current(): self
    {
        $root = defined('ONE_ENGINE_ROOT') ? ONE_ENGINE_ROOT : dirname(__DIR__, 2);
        $data = require $root . '/config/version.php';
        if (!is_array($data) || !preg_match('/\A\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?\z/', (string) ($data['version'] ?? ''))
            || !preg_match('/\A[0-9A-Za-z.-]{1,40}\z/', (string) ($data['build'] ?? ''))
            || strtotime((string) ($data['built_at'] ?? '')) === false) {
            throw new \RuntimeException('Invalid engine version metadata.');
        }
        return new self((string) $data['version'], (string) $data['build'], (string) $data['built_at']);
    }

    public function label(): string { return 'ONE Engine v' . $this->version; }
    public function toArray(): array { return ['version' => $this->version, 'build' => $this->build, 'built_at' => $this->builtAt]; }
}

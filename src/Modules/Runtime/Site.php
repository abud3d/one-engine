<?php
declare(strict_types=1);

namespace OneEngine\Modules\Runtime;

use OneEngine\Core\Identifier;

final readonly class Site
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $baseUrl,
        public Mode $mode = Mode::Dynamic,
        public bool $indexable = false,
    ) {
        Identifier::check($id);
        $url = parse_url($baseUrl);
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !is_array($url)
            || ($url['scheme'] ?? '') !== 'https' || empty($url['host'])
            || isset($url['user'], $url['pass']) || isset($url['user'])
            || isset($url['query']) || isset($url['fragment'])
            || !in_array($url['path'] ?? '', ['', '/'], true)) {
            throw new \InvalidArgumentException('Use a configured HTTPS site origin, without credentials, query or path.');
        }
        if ($title === '' || strlen($title) > 500 || strlen($description) > 2000
            || !preg_match('//u', $title . $description)) {
            throw new \InvalidArgumentException('Invalid site metadata.');
        }
    }

    public static function fromArray(array $config): self
    {
        return new self(
            $config['id'], $config['title'], $config['description'], $config['base_url'],
            Mode::from($config['mode']), $config['indexable'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'title' => $this->title, 'description' => $this->description,
            'base_url' => $this->baseUrl, 'mode' => $this->mode->value, 'indexable' => $this->indexable,
        ];
    }

    public function canonical(): string
    {
        return rtrim($this->baseUrl, '/') . '/';
    }
}

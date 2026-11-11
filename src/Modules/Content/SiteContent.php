<?php
declare(strict_types=1);

namespace OneEngine\Modules\Content;

use OneEngine\Core\{Action, Identifier};
use OneEngine\Modules\Identity\Access;
use OneEngine\Storage\DocumentStore;

final readonly class SiteContent
{
    public function __construct(private DocumentStore $store)
    {
    }

    /** @param array{id:string,role:string} $actor */
    public function saveSection(string $siteId, array $actor, array $input): void
    {
        $this->save($siteId, $actor, 'sections', $input, true);
    }

    /** @param array{id:string,role:string} $actor */
    public function savePage(string $siteId, array $actor, array $input): void
    {
        $this->save($siteId, $actor, 'cms_pages', $input, false);
    }

    /** @param array{id:string,role:string} $actor */
    public function saveMenu(string $siteId, array $actor, array $input): void
    {
        if (!Access::allows($actor, Action::Update)) {
            throw new \DomainException('Forbidden.');
        }
        $id = self::slug((string) ($input['id'] ?? ''));
        $label = self::text((string) ($input['label'] ?? ''), 120, false);
        $url = (string) ($input['url'] ?? '');
        if (!preg_match('~\A/(?:[a-z0-9/_-]*)\z~D', $url)) {
            throw new \InvalidArgumentException('Menu URL must be a local path.');
        }
        $position = (string) ($input['position'] ?? 'header');
        if (!in_array($position, ['header', 'sidebar', 'footer'], true)) {
            throw new \InvalidArgumentException('Invalid menu position.');
        }
        $visibility = self::visibility((string) ($input['visibility'] ?? 'public'));
        $this->replace($siteId, 'menus', $id, compact('id', 'label', 'url', 'position', 'visibility'));
    }

    /** @return list<array<string,mixed>> */
    public function list(string $siteId, string $kind, ?array $actor = null, bool $publishedOnly = false): array
    {
        Identifier::check($siteId);
        if (!in_array($kind, ['sections', 'cms_pages', 'menus'], true)) {
            throw new \InvalidArgumentException('Invalid content kind.');
        }
        $records = $this->store->read($siteId, $kind);
        if (!array_is_list($records)) {
            throw new \UnexpectedValueException('Invalid content index.');
        }
        $visible = [];
        foreach ($records as $record) {
            if (!is_array($record) || !isset($record['id'])) {
                throw new \UnexpectedValueException('Invalid content record.');
            }
            if ($publishedOnly && !Access::allows($actor, Action::Read, $record)) {
                continue;
            }
            $visible[] = $record;
        }
        return $visible;
    }

    public function findPage(string $siteId, string $slug, ?array $actor = null): ?array
    {
        $slug = self::slug($slug);
        foreach ($this->list($siteId, 'cms_pages') as $page) {
            if ($page['id'] === $slug) {
                return Access::allows($actor, Action::Read, $page) ? $page : null;
            }
        }
        return null;
    }

    private function save(string $siteId, array $actor, string $kind, array $input, bool $section): void
    {
        $id = self::slug((string) ($input['id'] ?? ''));
        $existing = null;
        foreach ($this->list($siteId, $kind) as $item) {
            if ($item['id'] === $id) {
                $existing = $item;
            }
        }
        $action = $existing === null ? Action::Create : Action::Update;
        if (!Access::allows($actor, $action, $existing)) {
            throw new \DomainException('Forbidden.');
        }
        $state = (string) ($input['state'] ?? 'draft');
        if (!in_array($state, ['draft', 'published'], true)) {
            throw new \InvalidArgumentException('Invalid state.');
        }
        if ($state === 'published' && !Access::allows($actor, Action::Publish, $existing)) {
            throw new \DomainException('Publishing forbidden.');
        }
        $title = self::text((string) ($input['title'] ?? ''), 200, false);
        $description = self::text((string) ($input['description'] ?? ''), 500, true);
        $visibility = self::visibility((string) ($input['visibility'] ?? 'public'));
        $layout = (string) ($input['layout'] ?? 'one');
        if (!in_array($layout, ['one', 'two', 'three'], true)) {
            throw new \InvalidArgumentException('Invalid layout.');
        }
        $now = gmdate('c');
        $record = compact('id', 'title', 'description', 'state', 'visibility', 'layout');
        $record['created_at'] = $existing['created_at'] ?? $now;
        $record['updated_at'] = $now;
        if (!$section) {
            $record['section_id'] = ($input['section_id'] ?? '') === '' ? null : self::slug((string) $input['section_id']);
            $record['body'] = self::text((string) ($input['body'] ?? ''), 100000, true);
            $record['blocks'] = self::blocks($input['blocks'] ?? []);
        }
        $this->replace($siteId, $kind, $id, $record);
    }

    private function replace(string $siteId, string $key, string $id, array $record): void
    {
        $this->store->update($siteId, $key, static function (array $records) use ($id, $record): array {
            if (!array_is_list($records)) {
                throw new \UnexpectedValueException('Invalid content index.');
            }
            $next = array_values(array_filter($records, static fn ($item): bool => is_array($item) && ($item['id'] ?? null) !== $id));
            if (count($next) >= 500) {
                throw new \LengthException('Content limit reached.');
            }
            $next[] = $record;
            return $next;
        });
    }

    private static function slug(string $value): string
    {
        if (!preg_match('/\A[a-z0-9][a-z0-9_-]{0,63}\z/D', $value)) {
            throw new \InvalidArgumentException('Invalid slug.');
        }
        return $value;
    }

    private static function text(string $value, int $max, bool $empty): string
    {
        if ((!$empty && trim($value) === '') || strlen($value) > $max || !preg_match('//u', $value)) {
            throw new \InvalidArgumentException('Invalid text.');
        }
        return $value;
    }

    private static function visibility(string $value): string
    {
        if (!in_array($value, ['public', 'members', 'private'], true)) {
            throw new \InvalidArgumentException('Invalid visibility.');
        }
        return $value;
    }

    /** @return list<array{type:string,content:string,visibility:string,column:int}> */
    private static function blocks(mixed $blocks): array
    {
        if (is_string($blocks)) {
            $blocks = $blocks === '' ? [] : json_decode($blocks, true, 32, JSON_THROW_ON_ERROR);
        }
        if (!is_array($blocks) || !array_is_list($blocks) || count($blocks) > 50) {
            throw new \InvalidArgumentException('Invalid blocks.');
        }
        $result = [];
        foreach ($blocks as $block) {
            if (!is_array($block) || !in_array(($block['type'] ?? ''), ['text', 'heading', 'image', 'links', 'section'], true)) {
                throw new \InvalidArgumentException('Invalid block.');
            }
            $content = self::text((string) ($block['content'] ?? ''), 5000, true);
            if ($block['type'] === 'image' && !preg_match('~\Ahttps://[^\s]+\z~D', $content)) {
                throw new \InvalidArgumentException('Images require HTTPS URLs.');
            }
            $column = (int) ($block['column'] ?? 1);
            if ($column < 1 || $column > 3) {
                throw new \InvalidArgumentException('Invalid block column.');
            }
            $result[] = ['type' => $block['type'], 'content' => $content, 'visibility' => self::visibility((string) ($block['visibility'] ?? 'public')), 'column' => $column];
        }
        return $result;
    }
}

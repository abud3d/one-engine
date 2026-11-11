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
        if (!Access::allows($actor, Action::Update, null, $siteId)) {
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

    /** @param array{id:string,role:string} $actor */
    public function saveTemplate(string $siteId, array $actor, array $input): void
    {
        if (!Access::allows($actor, Action::Update, null, $siteId)) {
            throw new \DomainException('Forbidden.');
        }
        $id = self::slug((string) ($input['id'] ?? ''));
        $title = self::text((string) ($input['title'] ?? ''), 120, false);
        $description = self::text((string) ($input['description'] ?? ''), 500, true);
        $layout = self::layout((string) ($input['layout'] ?? 'one'));
        $sidebar = ($input['sidebar'] ?? '') === '1';
        $this->replace($siteId, 'templates', $id, compact('id', 'title', 'description', 'layout', 'sidebar'));
    }

    /** @param array{id:string,role:string} $actor */
    public function saveMedia(string $siteId, array $actor, array $input): void
    {
        if (!Access::allows($actor, Action::Update, null, $siteId)) {
            throw new \DomainException('Forbidden.');
        }
        $id = self::slug((string) ($input['id'] ?? ''));
        $title = self::text((string) ($input['title'] ?? ''), 120, false);
        $alt = self::text((string) ($input['alt'] ?? ''), 300, true);
        $url = (string) ($input['url'] ?? '');
        if (!preg_match('~\Ahttps://[^\s<>"\']{1,2000}\z~D', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Media URL must use HTTPS.');
        }
        $this->replace($siteId, 'media', $id, compact('id', 'title', 'alt', 'url'));
    }

    /** @param array{id:string,role:string} $actor */
    public function delete(string $siteId, array $actor, string $kind, string $id): void
    {
        if (!Access::allows($actor, Action::Delete, null, $siteId) || !in_array($kind, ['sections', 'cms_pages', 'menus', 'templates', 'media'], true)) {
            throw new \DomainException('Forbidden.');
        }
        $id = self::slug($id);
        if ($kind === 'sections') {
            foreach ($this->list($siteId, 'cms_pages') as $page) {
                if (($page['section_id'] ?? null) === $id) {
                    throw new \DomainException('Section still contains pages.');
                }
            }
        }
        if ($kind === 'templates') {
            foreach ($this->list($siteId, 'cms_pages') as $page) {
                if (($page['template_id'] ?? null) === $id) {
                    throw new \DomainException('Template is still assigned to pages.');
                }
            }
        }
        if ($kind === 'media') {
            foreach ($this->list($siteId, 'media') as $medium) {
                if (($medium['id'] ?? null) !== $id) {
                    continue;
                }
                foreach ($this->list($siteId, 'cms_pages') as $page) {
                    foreach (($page['blocks'] ?? []) as $block) {
                        if (is_array($block) && ($block['content'] ?? null) === ($medium['url'] ?? null)) {
                            throw new \DomainException('Media is still used by a page.');
                        }
                    }
                }
            }
        }
        $this->store->update($siteId, $kind, static function (array $records) use ($id): array {
            if (!array_is_list($records)) {
                throw new \UnexpectedValueException('Invalid content index.');
            }
            $next = array_values(array_filter($records, static fn ($record): bool => is_array($record) && ($record['id'] ?? null) !== $id));
            if (count($next) === count($records)) {
                throw new \DomainException('Content not found.');
            }
            return $next;
        });
    }

    /** @return list<array<string,mixed>> */
    public function list(string $siteId, string $kind, ?array $actor = null, bool $publishedOnly = false): array
    {
        Identifier::check($siteId);
        if (!in_array($kind, ['sections', 'cms_pages', 'menus', 'templates', 'media'], true)) {
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
            if ($publishedOnly && !Access::allows($actor, Action::Read, $record, $siteId)) {
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
                return Access::allows($actor, Action::Read, $page, $siteId) ? $page : null;
            }
        }
        return null;
    }

    public function find(string $siteId, string $kind, string $id): ?array
    {
        $id = self::slug($id);
        foreach ($this->list($siteId, $kind) as $record) {
            if (($record['id'] ?? null) === $id) {
                return $record;
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
        if (!Access::allows($actor, $action, $existing, $siteId)) {
            throw new \DomainException('Forbidden.');
        }
        $state = (string) ($input['state'] ?? 'draft');
        if (!in_array($state, ['draft', 'published'], true)) {
            throw new \InvalidArgumentException('Invalid state.');
        }
        if ($state === 'published' && !Access::allows($actor, Action::Publish, $existing, $siteId)) {
            throw new \DomainException('Publishing forbidden.');
        }
        $title = self::text((string) ($input['title'] ?? ''), 200, false);
        $description = self::text((string) ($input['description'] ?? ''), 500, true);
        $visibility = self::visibility((string) ($input['visibility'] ?? 'public'));
        $layout = self::layout((string) ($input['layout'] ?? 'one'));
        $now = gmdate('c');
        $record = compact('id', 'title', 'description', 'state', 'visibility', 'layout');
        $record['created_at'] = $existing['created_at'] ?? $now;
        $record['updated_at'] = $now;
        if (!$section) {
            $record['section_id'] = ($input['section_id'] ?? '') === '' ? null : self::slug((string) $input['section_id']);
            $record['body'] = self::text((string) ($input['body'] ?? ''), 100000, true);
            $record['blocks'] = self::blocks($input['blocks'] ?? []);
            $record['allowed_users'] = self::identifiers($input['allowed_users'] ?? []);
            $record['allowed_editors'] = self::identifiers($input['allowed_editors'] ?? []);
            $record['template_id'] = ($input['template_id'] ?? '') === '' ? null : self::slug((string) $input['template_id']);
            if ($record['template_id'] !== null) {
                $template = $this->find($siteId, 'templates', $record['template_id']);
                if ($template === null) {
                    throw new \InvalidArgumentException('Selected template does not exist.');
                }
                $record['layout'] = (string) $template['layout'];
                $record['show_sidebar'] = (bool) ($template['sidebar'] ?? false);
            }
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

    private static function layout(string $value): string
    {
        if (!in_array($value, ['one', 'two', 'three'], true)) {
            throw new \InvalidArgumentException('Invalid layout.');
        }
        return $value;
    }

    /** @return list<string> */
    private static function identifiers(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $id): bool => $id !== ''));
        }
        if (!is_array($value) || !array_is_list($value) || count($value) > 50) {
            throw new \InvalidArgumentException('Invalid principal list.');
        }
        foreach ($value as $id) {
            if (!is_string($id)) {
                throw new \InvalidArgumentException('Invalid principal identifier.');
            }
            Identifier::check($id);
        }
        return array_values(array_unique($value));
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

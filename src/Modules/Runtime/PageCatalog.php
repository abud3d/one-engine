<?php
declare(strict_types=1);

namespace OneEngine\Modules\Runtime;

use OneEngine\Core\{Action, ContentObject, Identifier, PermissionEngine, Principal};
use OneEngine\Storage\DocumentStore;

/** Source-only first vertical slice. Does not run within exported site bundles. */
final readonly class PageCatalog
{
    public function __construct(private DocumentStore $store)
    {
    }

    public function save(string $siteId, string $slug, string $title, string $body, bool $publish): void
    {
        Identifier::check($siteId);
        self::validateSlug($slug);
        $page = new ContentObject($siteId, $slug, 'page', 'local-editor', $title, $body,
            $publish ? 'published' : 'draft', $publish ? 'public' : 'private');
        $this->store->update($siteId, 'pages', static function (array $pages) use ($page, $slug): array {
            if (array_is_list($pages) === false && $pages !== []) {
                throw new \UnexpectedValueException('Invalid page index.');
            }
            if (count($pages) >= 100 && !array_key_exists($slug, array_column($pages, null, 'id'))) {
                throw new \LengthException('Page limit reached.');
            }
            $updated = [];
            foreach ($pages as $item) {
                if (!is_array($item) || !isset($item['id']) || !is_string($item['id'])) {
                    throw new \UnexpectedValueException('Invalid page record.');
                }
                if ($item['id'] !== $slug) {
                    $updated[] = $item;
                }
            }
            $updated[] = [
                'id' => $page->id, 'title' => $page->title, 'body' => $page->body,
                'state' => $page->state, 'visibility' => $page->visibility,
            ];
            return $updated;
        });
    }

    /** @return list<ContentObject> */
    public function published(string $siteId): array
    {
        Identifier::check($siteId);
        $data = $this->store->read($siteId, 'pages');
        if (!array_is_list($data)) {
            throw new \UnexpectedValueException('Invalid page index.');
        }
        $policy = new PermissionEngine();
        $pages = [];
        foreach ($data as $entry) {
            if (!is_array($entry) || !isset($entry['id'], $entry['title'], $entry['body'], $entry['state'], $entry['visibility'])
                || !is_string($entry['id']) || !is_string($entry['title']) || !is_string($entry['body'])
                || !is_string($entry['state']) || !is_string($entry['visibility'])) {
                throw new \UnexpectedValueException('Invalid page record.');
            }
            self::validateSlug($entry['id']);
            $page = new ContentObject($siteId, $entry['id'], 'page', 'local-editor',
                $entry['title'], $entry['body'], $entry['state'], $entry['visibility']);
            if ($policy->allows(new Principal(), Action::Read, $page, $siteId)) {
                $pages[] = $page;
            }
        }
        return $pages;
    }

    private static function validateSlug(string $slug): void
    {
        if (!preg_match('/\A[a-z0-9][a-z0-9_-]{0,63}\z/D', $slug)) {
            throw new \InvalidArgumentException('Use a lowercase page slug.');
        }
    }
}

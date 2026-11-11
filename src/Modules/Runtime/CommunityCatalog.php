<?php
declare(strict_types=1);

namespace OneEngine\Modules\Runtime;

use OneEngine\Core\{Action, ContentObject, Identifier, PermissionEngine, Principal};
use OneEngine\Storage\DocumentStore;

/** Small read-only visitor community stream; local CLI is the only authoring entry. */
final readonly class CommunityCatalog
{
    public function __construct(private DocumentStore $store)
    {
    }

    public function save(string $siteId, string $slug, string $title, string $body, bool $publish): void
    {
        Identifier::check($siteId);
        self::slug($slug);
        $post = new ContentObject($siteId, $slug, 'post', 'local-editor', $title, $body,
            $publish ? 'published' : 'draft', $publish ? 'public' : 'private');
        $this->store->update($siteId, 'community-posts', static function (array $data) use ($post): array {
            if (!array_is_list($data)) {
                throw new \UnexpectedValueException('Invalid post index.');
            }
            if (count($data) >= 100 && !in_array($post->id, array_column($data, 'id'), true)) {
                throw new \LengthException('Post limit reached.');
            }
            $next = [];
            foreach ($data as $entry) {
                if (!is_array($entry) || !isset($entry['id']) || !is_string($entry['id'])) {
                    throw new \UnexpectedValueException('Invalid post record.');
                }
                if ($entry['id'] !== $post->id) {
                    $next[] = $entry;
                }
            }
            $next[] = ['id' => $post->id, 'title' => $post->title, 'body' => $post->body,
                'state' => $post->state, 'visibility' => $post->visibility];
            return $next;
        });
    }

    /** @return list<ContentObject> */
    public function published(string $siteId): array
    {
        Identifier::check($siteId);
        $data = $this->store->read($siteId, 'community-posts');
        if (!array_is_list($data)) {
            throw new \UnexpectedValueException('Invalid post index.');
        }
        $policy = new PermissionEngine();
        $posts = [];
        foreach ($data as $entry) {
            if (!is_array($entry) || !isset($entry['id'], $entry['title'], $entry['body'],
                $entry['state'], $entry['visibility']) || !is_string($entry['id'])
                || !is_string($entry['title']) || !is_string($entry['body'])
                || !is_string($entry['state']) || !is_string($entry['visibility'])) {
                throw new \UnexpectedValueException('Invalid post record.');
            }
            self::slug($entry['id']);
            $post = new ContentObject($siteId, $entry['id'], 'post', 'local-editor',
                $entry['title'], $entry['body'], $entry['state'], $entry['visibility']);
            if ($policy->allows(new Principal(), Action::Read, $post, $siteId)) {
                $posts[] = $post;
            }
        }
        return array_reverse($posts);
    }

    private static function slug(string $slug): void
    {
        if (!preg_match('/\A[a-z0-9][a-z0-9_-]{0,63}\z/D', $slug)) {
            throw new \InvalidArgumentException('Invalid post slug.');
        }
    }
}

<?php
declare(strict_types=1);

namespace OneEngine\Modules\Runtime;

use OneEngine\Modules\Identity\Access;
use OneEngine\Core\Action;

final class CmsRenderer
{
    public function page(Site $site, array $page, array $menus, ?array $actor): string
    {
        $e = Renderer::escape(...);
        $blocks = '';
        foreach (($page['blocks'] ?? []) as $block) {
            if (!is_array($block) || !Access::allows($actor, Action::Read, ['state' => 'published', 'visibility' => $block['visibility'] ?? 'private'])) {
                continue;
            }
            $content = (string) ($block['content'] ?? '');
            $rendered = match ($block['type'] ?? '') {
                'heading' => '<h2>' . $e($content) . '</h2>',
                'image' => '<img src="' . $e($content) . '" alt="" loading="lazy">',
                'links' => '<p><a href="' . $e($content) . '">' . $e($content) . '</a></p>',
                default => '<p>' . nl2br($e($content)) . '</p>',
            };
            $blocks .= '<article class="block col-' . (int) ($block['column'] ?? 1) . '">' . $rendered . '</article>';
        }
        $robots = $site->indexable && ($page['visibility'] ?? '') === 'public' ? 'index,follow' : 'noindex,nofollow';
        return '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e((string) $page['title']) . ' · ' . $e($site->title) . '</title><meta name="description" content="' . $e((string) ($page['description'] ?? '')) . '"><meta name="robots" content="' . $robots . '"><link rel="canonical" href="' . $e(rtrim($site->baseUrl, '/') . '/p/' . $page['id']) . '"><link rel="stylesheet" href="/assets/app.css"></head><body><header><a class="brand" href="/">ONE Engine</a><nav>' . $this->menus($menus, 'header', $actor) . '</nav></header><main><h1>' . $e((string) $page['title']) . '</h1><div class="cms-layout layout-' . $e((string) ($page['layout'] ?? 'one')) . '"><aside>' . $this->menus($menus, 'sidebar', $actor) . '</aside><div><p>' . nl2br($e((string) ($page['body'] ?? ''))) . '</p><section class="blocks">' . $blocks . '</section></div></div></main><footer>' . $this->menus($menus, 'footer', $actor) . '</footer></body></html>';
    }

    private function menus(array $menus, string $position, ?array $actor): string
    {
        $html = '';
        foreach ($menus as $menu) {
            if (($menu['position'] ?? '') !== $position || !Access::allows($actor, Action::Read, ['state' => 'published', 'visibility' => $menu['visibility'] ?? 'private'])) {
                continue;
            }
            $html .= '<a href="' . Renderer::escape((string) $menu['url']) . '">' . Renderer::escape((string) $menu['label']) . '</a> ';
        }
        return $html;
    }
}

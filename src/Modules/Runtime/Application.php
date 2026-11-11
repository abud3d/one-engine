<?php
declare(strict_types=1);

namespace OneEngine\Modules\Runtime;

use OneEngine\Core\ContentObject;
use OneEngine\Security\Headers;
use OneEngine\Modules\Content\SiteContent;

final readonly class Application
{
    public function __construct(private Site $site, private string $publicRoot, private ?PageCatalog $pages = null, private ?CommunityCatalog $community = null, private ?SiteContent $cms = null, private ?array $actor = null)
    {
    }

    public static function demoPage(Site $site): ContentObject
    {
        return new ContentObject($site->id, 'home', 'page', 'demo-author', $site->title,
            $site->description, 'published', 'public');
    }

    public function handle(string $method, string $uri, bool $https = false): Response
    {
        $headers = Headers::defaults($https);
        $headers['Content-Type'] = 'text/html; charset=utf-8';
        if (!$this->site->indexable) {
            $headers['X-Robots-Tag'] = 'noindex, nofollow';
        }
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return new Response(405, 'Method not allowed', $headers + ['Allow' => 'GET, HEAD']);
        }
        // Exact allowlist; no request-derived filesystem paths or dynamic includes.
        $path = explode('?', $uri, 2)[0];
        $renderer = new Renderer();
        // Source preview only: exported runtimes omit these preview assets.
        $preview = is_file($this->publicRoot . '/assets/preview-shell.css')
            && is_file($this->publicRoot . '/assets/preview-shell.js');
        $communityLink = is_file($this->publicRoot . '/community/community.html')
            ? '/community/community.html' : '/community';
        if ($path === '/') {
            $body = $renderer->home($this->site, self::demoPage($this->site));
            if ($preview) {
                $body = str_replace('</header>',
                    '<nav class="one-home-nav" aria-label="القائمة الرئيسية"><a href="/">الرئيسية</a><a href="/pages">الصفحات</a><a href="' . $communityLink . '">المجتمع</a><a href="/community/login.html">الدخول</a><a href="/community/admin.html">الإدارة</a></nav></header>', $body);
                $body = str_replace('</main>',
                    '<div class="actions"><a class="button" href="/pages">الصفحات</a><a class="button" href="' . $communityLink . '">المجتمع الكامل</a><a class="button" href="/community/login.html">دخول الأعضاء</a><a class="button" href="/community/admin.html">لوحة الإدارة</a></div></main>', $body);
            }
            return new Response(200, $body, $headers);
        }
        if ($this->cms !== null && preg_match('~\A/p/([a-z0-9][a-z0-9_-]{0,63})\z~D', $path, $matched)) {
            $page = $this->cms->findPage($this->site->id, $matched[1], $this->actor);
            return $page === null
                ? new Response(404, 'Not found', $headers)
                : new Response(200, (new CmsRenderer())->page($this->site, $page, $this->cms->list($this->site->id, 'menus'), $this->actor), $headers);
        }
        if ($preview && $path === '/pages') {
            $headers['Content-Security-Policy'] = str_replace("style-src 'self';", "style-src 'self'; script-src 'self';", $headers['Content-Security-Policy']);
            return new Response(200, (new PagePreview())->index($this->site, ($this->pages?->published($this->site->id) ?? [])), $headers);
        }
        if ($preview && $this->pages !== null && preg_match('~\\A/pages/([a-z0-9][a-z0-9_-]{0,63})\\z~D', $path, $matched)) {
            foreach ($this->pages->published($this->site->id) as $page) {
                if ($page->id === $matched[1]) {
                    $headers['Content-Security-Policy'] = str_replace("style-src 'self';", "style-src 'self'; script-src 'self';", $headers['Content-Security-Policy']);
                    return new Response(200, (new PagePreview())->page($this->site, $page), $headers);
                }
            }
            return new Response(404, 'Not found', $headers);
        }
        if ($preview && $path === '/community') {
            $headers['Content-Security-Policy'] = str_replace("style-src 'self';", "style-src 'self'; script-src 'self';", $headers['Content-Security-Policy']);
            return new Response(200, (new PagePreview())->community($this->site,
                $this->community?->published($this->site->id) ?? []), $headers);
        }
        if ($preview && $this->community !== null && preg_match('~\\A/community/([a-z0-9][a-z0-9_-]{0,63})\\z~D', $path, $matched)) {
            foreach ($this->community->published($this->site->id) as $post) {
                if ($post->id === $matched[1]) {
                    $headers['Content-Security-Policy'] = str_replace("style-src 'self';", "style-src 'self'; script-src 'self';", $headers['Content-Security-Policy']);
                    return new Response(200, (new PagePreview())->post($this->site, $post), $headers);
                }
            }
            return new Response(404, 'Not found', $headers);
        }
        if ($path === '/health' && $this->site->mode !== Mode::Static) {
            $headers['Content-Type'] = 'application/json; charset=utf-8';
            return new Response(200, '{"status":"ok"}', $headers);
        }
        if ($path === '/robots.txt') {
            $headers['Content-Type'] = 'text/plain; charset=utf-8';
            return new Response(200, $renderer->robots($this->site), $headers);
        }
        if ($path === '/sitemap.xml') {
            $headers['Content-Type'] = 'application/xml; charset=utf-8';
            if ($this->cms !== null && $this->site->indexable) {
                $urls = [$this->site->canonical()];
                foreach ($this->cms->list($this->site->id, 'cms_pages', null, true) as $page) {
                    $urls[] = rtrim($this->site->baseUrl, '/') . '/p/' . $page['id'];
                }
                $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
                foreach ($urls as $url) {
                    $xml .= '<url><loc>' . Renderer::escape($url) . '</loc></url>';
                }
                return new Response(200, $xml . '</urlset>', $headers);
            }
            return new Response(200, $renderer->sitemap($this->site), $headers);
        }
        if ($preview && in_array($path, ['/assets/preview-shell.css', '/assets/preview-shell.js'], true)) {
            $headers['Content-Type'] = str_ends_with($path, '.css') ? 'text/css; charset=utf-8' : 'text/javascript; charset=utf-8';
            $body = file_get_contents($this->publicRoot . $path);
            if ($body === false) {
                throw new \RuntimeException('Preview asset unavailable.');
            }
            return new Response(200, $body, $headers);
        }
        if ($path === '/assets/app.css') {
            $headers['Content-Type'] = 'text/css; charset=utf-8';
            $css = file_get_contents($this->publicRoot . '/assets/app.css');
            if ($css === false) {
                throw new \RuntimeException('Asset unavailable.');
            }
            return new Response(200, $css, $headers);
        }
        return new Response(404, 'Not found', $headers);
    }
}

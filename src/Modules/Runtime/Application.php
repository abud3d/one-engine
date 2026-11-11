<?php
declare(strict_types=1);

namespace OneEngine\Modules\Runtime;

use OneEngine\Core\ContentObject;
use OneEngine\Security\Headers;

final readonly class Application
{
    public function __construct(private Site $site, private string $publicRoot, private ?PageCatalog $pages = null)
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
        if ($path === '/') {
            $body = $renderer->home($this->site, self::demoPage($this->site));
            if ($this->pages !== null) {
                $body = str_replace('</main>', '<div class="actions"><a class="button" href="/pages">تصفح الصفحات المنشورة</a></div></main>', $body);
            }
            return new Response(200, $body, $headers);
        }
        if ($this->pages !== null && $path === '/pages') {
            $headers['Content-Security-Policy'] = str_replace("style-src 'self';", "style-src 'self'; script-src 'self';", $headers['Content-Security-Policy']);
            return new Response(200, (new PagePreview())->index($this->site, $this->pages->published($this->site->id)), $headers);
        }
        if ($this->pages !== null && preg_match('~\\A/pages/([a-z0-9][a-z0-9_-]{0,63})\\z~D', $path, $matched)) {
            foreach ($this->pages->published($this->site->id) as $page) {
                if ($page->id === $matched[1]) {
                    $headers['Content-Security-Policy'] = str_replace("style-src 'self';", "style-src 'self'; script-src 'self';", $headers['Content-Security-Policy']);
                    return new Response(200, (new PagePreview())->page($this->site, $page), $headers);
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
            return new Response(200, $renderer->sitemap($this->site), $headers);
        }
        if ($this->pages !== null && in_array($path, ['/assets/preview-shell.css', '/assets/preview-shell.js'], true)) {
            $headers['Content-Type'] = str_ends_with($path, '.css') ? 'text/css; charset=utf-8' : 'text/javascript; charset=utf-8';
            $body = file_get_contents($this->publicRoot . $path);
            if ($body === false) {
                throw new \\RuntimeException('Preview asset unavailable.');
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

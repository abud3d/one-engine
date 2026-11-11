<?php
declare(strict_types=1);

namespace OneEngine\Modules\Runtime;

use OneEngine\Core\ContentObject;
use OneEngine\Security\Headers;

final readonly class Application
{
    public function __construct(private Site $site, private string $publicRoot)
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
            return new Response(200, $renderer->home($this->site, self::demoPage($this->site)), $headers);
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

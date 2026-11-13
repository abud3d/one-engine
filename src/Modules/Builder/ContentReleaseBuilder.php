<?php
declare(strict_types=1);

namespace OneEngine\Modules\Builder;

use OneEngine\Modules\Content\SiteContent;
use OneEngine\Modules\Runtime\{CmsRenderer, Renderer, Site};

final readonly class ContentReleaseBuilder
{
    public function __construct(private string $projectRoot, private SiteContent $content)
    {
    }

    public function build(Site $site, string $destination): void
    {
        $parent = realpath(dirname($destination));
        if ($parent === false || file_exists($destination) || is_link($destination) || in_array(basename($destination), ['', '.', '..'], true)) {
            throw new \InvalidArgumentException('Choose a new export directory.');
        }
        $destination = $parent . '/' . basename($destination);
        $stage = $parent . '/.one-export-' . bin2hex(random_bytes(10));
        if (!mkdir($stage, 0700)) {
            throw new \RuntimeException('Cannot create export staging directory.');
        }
        try {
            $pages = $this->content->list($site->id, 'cms_pages', null, true);
            $menus = $this->content->list($site->id, 'menus');
            $links = '';
            $urls = [];
            foreach ($pages as $page) {
                $slug = (string) $page['id'];
                $links .= '<li><a href="p/' . Renderer::escape($slug) . '/">' . Renderer::escape((string) $page['title']) . '</a></li>';
                $html = (new CmsRenderer())->page($site, $page, $menus, null);
                $html = str_replace(['href="/assets/app.css"', 'href="/"', 'href="/p/'], ['href="../../assets/app.css"', 'href="../../"', 'href="../'], $html);
                $html = preg_replace('~href="\.\./([a-z0-9_-]+)"~', 'href="../$1/"', $html) ?? $html;
                $this->write($stage, 'p/' . $slug . '/index.html', $html);
                $urls[] = rtrim($site->baseUrl, '/') . '/p/' . $slug;
            }
            $home = '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . Renderer::escape($site->title) . '</title><meta name="description" content="' . Renderer::escape($site->description) . '"><link rel="canonical" href="' . Renderer::escape($site->canonical()) . '"><link rel="stylesheet" href="assets/app.css"></head><body><main><h1>' . Renderer::escape($site->title) . '</h1><ul>' . $links . '</ul></main></body></html>';
            $this->write($stage, 'index.html', $home);
            $this->write($stage, 'assets/app.css', (string) file_get_contents($this->projectRoot . '/public/assets/app.css'));
            $this->write($stage, 'robots.txt', $site->indexable ? "User-agent: *\nAllow: /\nSitemap: " . rtrim($site->baseUrl, '/') . "/sitemap.xml\n" : "User-agent: *\nDisallow: /\n");
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach ($urls as $url) {
                $xml .= '<url><loc>' . Renderer::escape($url) . '</loc></url>';
            }
            $this->write($stage, 'sitemap.xml', $xml . '</urlset>');
            $files = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($stage, \FilesystemIterator::SKIP_DOTS)) as $file) {
                $relative = substr($file->getPathname(), strlen($stage) + 1);
                $files[$relative] = hash_file('sha256', $file->getPathname());
            }
            ksort($files);
            $this->write($stage, 'release.json', json_encode(['schema' => 1, 'engine' => '0.2.0', 'site_id' => $site->id, 'mode' => 'static', 'central_required' => false, 'public_pages' => count($pages), 'files' => $files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if (!rename($stage, $destination)) {
                throw new \RuntimeException('Cannot promote export.');
            }
        } finally {
            if (is_dir($stage)) {
                $this->remove($stage);
            }
        }
    }

    public function zip(string $directory, string $archive): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('PHP zip extension is required to create ZIP archives.');
        }
        if (file_exists($archive)) {
            throw new \InvalidArgumentException('Archive already exists.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            throw new \RuntimeException('Cannot create ZIP archive.');
        }
        try {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (!$file->isFile() || $file->isLink()) {
                    throw new \RuntimeException('Unsafe export entry.');
                }
                $relative = substr($file->getPathname(), strlen(rtrim($directory, '/')) + 1);
                if (!$zip->addFile($file->getPathname(), $relative)) {
                    throw new \RuntimeException('Cannot add ZIP entry.');
                }
            }
        } finally {
            $zip->close();
        }
        chmod($archive, 0600);
    }

    private function write(string $root, string $relative, string $content): void
    {
        $path = $root . '/' . $relative;
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) {
            throw new \RuntimeException('Cannot create export directory.');
        }
        if (file_put_contents($path, $content, LOCK_EX) !== strlen($content) || !chmod($path, 0600)) {
            throw new \RuntimeException('Incomplete export write.');
        }
    }

    private function remove(string $path): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($path);
    }
}

<?php
declare(strict_types=1);

namespace OneEngine\Modules\Builder;

use OneEngine\Modules\Runtime\{Application, Mode, Renderer, Site};
use OneEngine\Security\Headers;

final readonly class ReleaseBuilder
{
    public function __construct(private string $projectRoot)
    {
    }

    /** Build synthetic foundation pages; no database, secrets or arbitrary directory copies. */
    public function build(Site $site, string $destination): void
    {
        $parent = realpath(dirname($destination));
        if ($parent === false || file_exists($destination) || is_link($destination)
            || in_array(basename($destination), ['', '.', '..'], true)) {
            throw new \InvalidArgumentException('Choose a new release directory in an existing parent.');
        }
        $destination = $parent . '/' . basename($destination);
        $stage = $parent . '/.one-build-' . bin2hex(random_bytes(12));
        if (!mkdir($stage, 0700)) {
            throw new \RuntimeException('Cannot create build directory.');
        }
        try {
            $this->write($stage, 'public/assets/app.css', $this->source('public/assets/app.css'));
            $renderer = new Renderer();
            $this->write($stage, 'public/robots.txt', $renderer->robots($site));
            $this->write($stage, 'public/sitemap.xml', $renderer->sitemap($site));
            if ($site->mode !== Mode::Dynamic) {
                $this->write($stage, 'public/index.html', $renderer->home($site, Application::demoPage($site)));
            }
            if ($site->mode !== Mode::Static) {
                $files = ['bootstrap.php', 'config/php.ini', 'public/index.php', 'public/router.php', 'src/Security/Headers.php'];
                foreach (['Identifier', 'Action', 'ContentObject', 'Principal', 'Grant', 'PermissionEngine'] as $class) {
                    $files[] = 'src/Core/' . $class . '.php';
                }
                foreach (['Mode', 'Site', 'Renderer', 'Response', 'Application', 'LocalConfiguration'] as $class) {
                    $files[] = 'src/Modules/Runtime/' . $class . '.php';
                }
                foreach ($files as $file) {
                    $this->write($stage, $file, $this->source($file));
                }
                $this->write($stage, 'config/site.php', "<?php\ndeclare(strict_types=1);\nreturn " . var_export($site->toArray(), true) . ";\n");
            }
            $this->write($stage, 'NOTICE.md', $this->source('NOTICE.md'));
            $this->write($stage, 'server-headers.json', json_encode(Headers::defaults(true), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $files = [];
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($stage, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $relative = substr($file->getPathname(), strlen($stage) + 1);
                $files[$relative] = hash_file('sha256', $file->getPathname());
            }
            ksort($files);
            $this->write($stage, 'release.json', json_encode([
                'schema' => 1, 'engine' => '0.1.0', 'site_id' => $site->id, 'mode' => $site->mode->value,
                'document_root' => 'public', 'central_required' => false, 'files' => $files,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            // Do not replace an existing release, even if another build won the race.
            if (file_exists($destination) || is_link($destination) || !rename($stage, $destination)) {
                throw new \RuntimeException('Cannot publish local build directory.');
            }
        } finally {
            if (is_dir($stage)) {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($stage, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($iterator as $file) {
                    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                }
                rmdir($stage);
            }
        }
    }

    private function source(string $path): string
    {
        $content = file_get_contents($this->projectRoot . '/' . $path);
        if ($content === false) {
            throw new \RuntimeException('Required build source is missing.');
        }
        return $content;
    }

    private function write(string $root, string $relative, string $content): void
    {
        $path = $root . '/' . $relative;
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) {
            throw new \RuntimeException('Cannot create release directory.');
        }
        if (file_put_contents($path, $content) !== strlen($content) || !chmod($path, 0600)) {
            throw new \RuntimeException('Incomplete release file.');
        }
    }
}

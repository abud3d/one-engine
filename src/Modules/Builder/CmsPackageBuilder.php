<?php
declare(strict_types=1);

namespace OneEngine\Modules\Builder;

final readonly class CmsPackageBuilder
{
    public function __construct(private string $projectRoot)
    {
    }

    public function build(string $destination): void
    {
        $parent = realpath(dirname($destination));
        if ($parent === false || file_exists($destination) || is_link($destination) || in_array(basename($destination), ['', '.', '..'], true)) {
            throw new \InvalidArgumentException('Choose a new CMS package directory.');
        }
        $destination = $parent . '/' . basename($destination);
        $stage = $parent . '/.one-cms-' . bin2hex(random_bytes(10));
        if (!mkdir($stage, 0700)) {
            throw new \RuntimeException('Cannot create CMS package staging directory.');
        }
        try {
            foreach (['bootstrap.php', 'NOTICE.md', 'config/php.ini', 'config/site.php',
                'public/index.php', 'public/admin.php', 'public/router.php', 'public/.htaccess',
                'public/assets/app.css', 'public/assets/admin.css', 'public/assets/admin.js',
                'bin/one', 'bin/one-admin', 'bin/one-install'] as $file) {
                $this->copy($stage, $file);
            }
            foreach (['Core', 'Storage', 'Security', 'Modules'] as $directory) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->projectRoot . '/src/' . $directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
                    if ($file->isFile() && !$file->isLink() && $file->getExtension() === 'php') {
                        $relative = substr($file->getPathname(), strlen($this->projectRoot) + 1);
                        $this->copy($stage, $relative);
                    }
                }
            }
            $this->write($stage, 'INSTALL.md', $this->instructions());
            $files = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($stage, \FilesystemIterator::SKIP_DOTS)) as $file) {
                $relative = substr($file->getPathname(), strlen($stage) + 1);
                $files[$relative] = hash_file('sha256', $file->getPathname());
            }
            ksort($files);
            $this->write($stage, 'package.json', json_encode([
                'schema' => 1, 'product' => 'ONE Engine CMS', 'engine' => '0.2.0',
                'php' => '>=8.3', 'document_root' => 'public', 'private_data_included' => false,
                'credentials_included' => false, 'files' => $files,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if (!rename($stage, $destination)) {
                throw new \RuntimeException('Cannot promote CMS package.');
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
            throw new \RuntimeException('PHP zip extension is required only to build the archive.');
        }
        if (file_exists($archive)) {
            throw new \InvalidArgumentException('Archive already exists.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            throw new \RuntimeException('Cannot create CMS ZIP.');
        }
        try {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (!$file->isFile() || $file->isLink()) {
                    throw new \RuntimeException('Unsafe CMS package entry.');
                }
                $relative = 'one-engine-cms/' . substr($file->getPathname(), strlen(rtrim($directory, '/')) + 1);
                if (!$zip->addFile($file->getPathname(), $relative)) {
                    throw new \RuntimeException('Cannot add CMS ZIP entry.');
                }
            }
        } finally {
            $zip->close();
        }
        chmod($archive, 0600);
    }

    private function copy(string $stage, string $relative): void
    {
        $content = file_get_contents($this->projectRoot . '/' . $relative);
        if ($content === false) {
            throw new \RuntimeException('Missing CMS package source: ' . $relative);
        }
        $this->write($stage, $relative, $content, str_starts_with($relative, 'bin/') ? 0700 : 0600);
    }

    private function write(string $stage, string $relative, string $content, int $mode = 0600): void
    {
        $path = $stage . '/' . $relative;
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) {
            throw new \RuntimeException('Cannot create CMS package directory.');
        }
        if (file_put_contents($path, $content, LOCK_EX) !== strlen($content) || !chmod($path, $mode)) {
            throw new \RuntimeException('Incomplete CMS package file.');
        }
    }

    private function instructions(): string
    {
        return <<<'MD'
# ONE Engine CMS — installation

Requirements: PHP 8.3+, JSON and sessions. No Node, Composer or database is required. Configure the hosting document root as the extracted `one-engine-cms/public` directory; never expose the package root.

Create the first administrator from a terminal or hosting control-panel terminal. Choose a new private directory outside `public` and a password of at least 12 bytes:

```sh
cd /path/to/one-engine-cms
read -rsp 'Admin password: ' ONE_ADMIN_PASSWORD
echo
export ONE_ADMIN_PASSWORD
php bin/one-install /path/to/private-one-data admin 'https://YOUR-DOMAIN'
unset ONE_ADMIN_PASSWORD
```

The installer creates `config/local.php` with mode `0600`, stores the trusted HTTPS Origin, and creates the data directory with mode `0700`. It does not store the supplied password. Do not move `config/local.php` or the private directory into `public`.

Open `/admin`, sign in, then create a section, page, blocks and menu. Save the page as draft, use its protected preview link, then publish it. Public pages use `/p/SLUG`; section pages use `/sections/SLUG`.

For CWP: select PHP-FPM 8.3 for the domain, enable HTTPS, extract the package outside `public_html`, and set the domain Document Root to the extracted `one-engine-cms/public`. If the hosting plan cannot set that Document Root, stop and ask the host; do not expose the whole package under `public_html`. Apache must allow `mod_rewrite` and this package's `public/.htaccess`.

To export a separate static site, install the optional PHP `zip` extension only if a ZIP is required:

```sh
php bin/one export /path/to/private-one-data /new/export-directory /new/site.zip
```

This package contains no account, password, private data, production database or deployment command.
MD;
    }

    private function remove(string $path): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($path);
    }
}

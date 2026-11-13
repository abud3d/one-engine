<?php
declare(strict_types=1);

use OneEngine\Modules\Builder\CmsPackageBuilder;

test('interactive CMS package contains runtime and administration but no data or credentials', function () use ($temp): void {
    $destination = $temp . '/cms-package';
    (new CmsPackageBuilder(dirname(__DIR__)))->build($destination);
    foreach (['bootstrap.php', 'public/index.php', 'public/admin.php', 'public/router.php', 'public/.htaccess',
        'bin/one-install', 'bin/one', 'config/version.php', 'src/Core/EngineVersion.php', 'src/Modules/Content/SiteContent.php',
        'src/Modules/Identity/IdentityService.php', 'src/Modules/CentralManagement/AdminApplication.php',
        'src/Modules/Builder/ContentReleaseBuilder.php', 'INSTALL.md', 'package.json'] as $file) {
        check(is_file($destination . '/' . $file), 'Missing CMS package file: ' . $file);
    }
    check(!is_dir($destination . '/private') && !is_dir($destination . '/var'));
    check(!is_dir($destination . '/public/community') && !is_dir($destination . '/src/LegacyArbnt'));
    check(!is_file($destination . '/config/local.php'));
    $manifest = json_decode((string) file_get_contents($destination . '/package.json'), true, 32, JSON_THROW_ON_ERROR);
    check($manifest['product'] === 'ONE Engine CMS');
    check($manifest['engine'] === \OneEngine\Core\EngineVersion::current()->version);
    check($manifest['private_data_included'] === false && $manifest['credentials_included'] === false);
    $all = '';
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destination, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) {
            $all .= (string) file_get_contents($file->getPathname());
        }
    }
    check(!str_contains($all, 'change-this-long-password'));
});

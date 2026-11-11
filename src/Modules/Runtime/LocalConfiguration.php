<?php
declare(strict_types=1);

namespace OneEngine\Modules\Runtime;

final class LocalConfiguration
{
    public static function privateDirectory(string $projectRoot): ?string
    {
        $environment = getenv('ONE_PRIVATE_DIR');
        if (is_string($environment) && $environment !== '') {
            return $environment;
        }
        $file = $projectRoot . '/config/local.php';
        if (!is_file($file) || is_link($file)) {
            return null;
        }
        $config = require $file;
        if (!is_array($config) || !isset($config['private_dir']) || !is_string($config['private_dir']) || $config['private_dir'] === '') {
            throw new \UnexpectedValueException('Invalid local configuration.');
        }
        return $config['private_dir'];
    }
}

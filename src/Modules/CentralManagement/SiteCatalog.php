<?php
declare(strict_types=1);

namespace OneEngine\Modules\CentralManagement;

use OneEngine\Modules\Runtime\Site;

/** In-memory foundation boundary, not a deployed management API. */
final class SiteCatalog
{
    private array $sites = [];

    public function register(Site $site): void
    {
        if (isset($this->sites[$site->id])) {
            throw new \DomainException('Site is already registered.');
        }
        $this->sites[$site->id] = ['id' => $site->id, 'title' => $site->title, 'mode' => $site->mode->value];
    }

    public function all(): array
    {
        return array_values($this->sites);
    }
}

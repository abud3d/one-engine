<?php
declare(strict_types=1);

namespace OneEngine\Modules\Runtime;

enum Mode: string
{
    case Static = 'static';
    case Hybrid = 'hybrid';
    case Dynamic = 'dynamic';
}

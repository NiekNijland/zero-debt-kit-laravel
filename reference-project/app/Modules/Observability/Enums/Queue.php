<?php

declare(strict_types=1);

namespace App\Modules\Observability\Enums;

enum Queue: string
{
    case DEFAULT = 'default';
    case EXPORTS = 'exports';
    case IMPORTS = 'imports';
    case MAIL = 'mail';
}

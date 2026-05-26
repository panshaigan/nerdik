<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityLogoSource: string
{
    case Tag = 'tag';
    case Upload = 'upload';
}

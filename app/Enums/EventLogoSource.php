<?php

declare(strict_types=1);

namespace App\Enums;

enum EventLogoSource: string
{
    case Default = 'default';
    case Upload = 'upload';
}

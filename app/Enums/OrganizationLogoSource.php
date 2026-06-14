<?php

declare(strict_types=1);

namespace App\Enums;

enum OrganizationLogoSource: string
{
    case Generated = 'generated';
    case Upload = 'upload';
}

<?php

declare(strict_types=1);

namespace App\Filament\Admin\Enums;

use Filament\Support\Contracts\Collapsible;
use Filament\Support\Contracts\HasLabel;

/**
 * Case order controls Filament sidebar group order.
 */
enum AdminNavigationGroup implements Collapsible, HasLabel
{
    case Users;
    case Events;
    case Activities;
    case Catalog;
    case Geography;
    case Tags;
    case Communication;
    case Moderation;

    public function getLabel(): string
    {
        return match ($this) {
            self::Users => 'Users',
            self::Events => 'Events',
            self::Activities => 'Activities',
            self::Catalog => 'Catalog',
            self::Geography => 'Geography',
            self::Tags => 'Tags',
            self::Communication => 'Communication',
            self::Moderation => 'Moderation',
        };
    }

    public function isCollapsible(): bool
    {
        return true;
    }

    public function isCollapsed(): bool
    {
        return true;
    }
}

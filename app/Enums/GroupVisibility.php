<?php

declare(strict_types=1);

namespace App\Enums;

enum GroupVisibility: string
{
    case Private = 'private';
    case Public = 'public';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Private => __('ui.groups.visibility_private'),
            self::Public => __('ui.groups.visibility_public'),
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Private => __('ui.groups.visibility_private_hint'),
            self::Public => __('ui.groups.visibility_public_hint'),
        };
    }
}

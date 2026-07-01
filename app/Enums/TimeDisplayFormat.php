<?php

declare(strict_types=1);

namespace App\Enums;

enum TimeDisplayFormat: string
{
    case TwelveHour = '12h';
    case TwentyFourHour = '24h';

    /**
     * @return list<array{id: string, name: string}>
     */
    public static function optionsForSelect(): array
    {
        return array_map(
            fn (self $format): array => [
                'id' => $format->value,
                'name' => $format->label(),
            ],
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::TwelveHour => __('ui.profile.time_display_format_12h'),
            self::TwentyFourHour => __('ui.profile.time_display_format_24h'),
        };
    }
}

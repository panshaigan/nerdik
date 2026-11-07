<?php

declare(strict_types=1);

namespace App\Enums;

enum AvailabilityStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Uncertain = 'uncertain';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('ui.availability.status_available'),
            self::Unavailable => __('ui.availability.status_unavailable'),
            self::Uncertain => __('ui.availability.status_uncertain'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

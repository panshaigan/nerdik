<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case Planned = 'planned';
    case Cancelled = 'cancelled';
    case Finished = 'finished';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

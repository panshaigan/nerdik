<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRequestResolutionOutcome: string
{
    case Joined = 'joined';
    case Waitlisted = 'waitlisted';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

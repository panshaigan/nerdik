<?php

namespace App\Enums;

/**
 * Canonical activity type strings for activities, slots, and validation.
 */
enum ActivityType: string
{
    case Rpg = 'rpg';
    case Wargame = 'wargame';
    case Board = 'board';
    case Card = 'card';
    case Larp = 'larp';
    case Discussion = 'discussion';
    case Lecture = 'lecture';
    case Workshop = 'workshop';
    case Competition = 'competition';
    case Show = 'show';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

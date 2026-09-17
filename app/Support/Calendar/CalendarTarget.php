<?php

declare(strict_types=1);

namespace App\Support\Calendar;

enum CalendarTarget: string
{
    case Google = 'google';
    case Outlook = 'outlook';
    case Download = 'download';

    public function isExternal(): bool
    {
        return match ($this) {
            self::Google, self::Outlook => true,
            self::Download => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function menuCases(): array
    {
        return [
            self::Google,
            self::Outlook,
            self::Download,
        ];
    }
}

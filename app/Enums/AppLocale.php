<?php

declare(strict_types=1);

namespace App\Enums;

enum AppLocale: string
{
    case En = 'en';
    case Pl = 'pl';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isSupported(?string $locale): bool
    {
        return is_string($locale) && self::tryFrom($locale) instanceof self;
    }

    public static function coerce(?string $locale): self
    {
        return self::tryFrom((string) $locale) ?? self::En;
    }

    public function other(): self
    {
        return match ($this) {
            self::En => self::Pl,
            self::Pl => self::En,
        };
    }
}

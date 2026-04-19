<?php

namespace App\Enums;

enum BadgeSemantic: int
{
    case Primary = 1;
    case Secondary = 2;
    case Accent = 3;
    case Neutral = 4;
    case Info = 5;
    case Success = 6;
    case Warning = 7;
    case Error = 8;

    /**
     * @param  int|string|null  $value  Enum int value, numeric string, or case-insensitive name (e.g. "info").
     */
    public static function fromConfig(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }
        if ($value === null || $value === '') {
            return self::Primary;
        }
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return self::from((int) $value);
        }
        if (! is_string($value)) {
            return self::Primary;
        }
        $needle = strtolower(trim($value));
        foreach (self::cases() as $case) {
            if (strtolower($case->name) === $needle) {
                return $case;
            }
        }

        return self::Primary;
    }

    /**
     * DaisyUI badge utility classes (outline variant matches existing activity/event UI).
     */
    public function badgeClasses(bool $outline = true): string
    {
        $tone = match ($this) {
            self::Primary => 'badge-primary',
            self::Secondary => 'badge-secondary',
            self::Accent => 'badge-accent',
            self::Neutral => 'badge-neutral',
            self::Info => 'badge-info',
            self::Success => 'badge-success',
            self::Warning => 'badge-warning',
            self::Error => 'badge-error',
        };

        $outlineClass = $outline ? 'badge-outline' : '';

        return trim('badge '.$tone.' '.$outlineClass);
    }
}

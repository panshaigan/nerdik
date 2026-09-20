<?php

namespace App\Enums;

enum FamiliarityLevel: string
{
    case None = 'none';
    case Beginner = 'beginner';
    case Experienced = 'experienced';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return __('ui.familiarity.levels.'.$this->value);
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public static function radioOptions(): array
    {
        return array_map(
            static fn (self $case): array => [
                'id' => $case->value,
                'name' => $case->label(),
            ],
            self::cases(),
        );
    }

    public static function tryFromMixed(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}

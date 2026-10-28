<?php

declare(strict_types=1);

namespace App\Support\Ui;

final class GeneratedAvatarColors
{
    public static function defaultBackground(): string
    {
        return '#1d4ed8';
    }

    public static function defaultText(): string
    {
        return '#ffffff';
    }

    /**
     * @return list<string>
     */
    public static function palette(): array
    {
        return [
            '#7c3aed',
            '#9333ea',
            '#db2777',
            '#dc2626',
            '#ea580c',
            '#ca8a04',
            '#16a34a',
            '#059669',
            '#0891b2',
            '#0284c7',
            '#4f46e5',
            '#be123c',
        ];
    }

    /**
     * @return array{background: string, text: string}
     */
    public static function random(): array
    {
        $palette = self::palette();
        $index = random_int(0, count($palette) - 1);

        return [
            'background' => $palette[$index],
            'text' => self::defaultText(),
        ];
    }

    public static function usesDefaultColors(?string $background, ?string $text): bool
    {
        if ($background === null && $text === null) {
            return true;
        }

        return self::normalizeHex($background) === self::normalizeHex(self::defaultBackground())
            && self::normalizeHex($text) === self::normalizeHex(self::defaultText());
    }

    private static function normalizeHex(?string $color): ?string
    {
        if ($color === null) {
            return null;
        }

        return strtolower($color);
    }
}

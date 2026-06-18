<?php

declare(strict_types=1);

namespace App\Support\Ui;

enum AvatarSlot: string
{
    case Badge = 'badge';
    case Hero = 'hero';
    case Preview = 'preview';

    public function conversionName(): string
    {
        return match ($this) {
            self::Hero => 'avatar_118',
            self::Preview => 'avatar_512',
            self::Badge => 'avatar_32',
        };
    }

    public function displaySize(): int
    {
        return match ($this) {
            self::Hero => 118,
            self::Preview => 512,
            self::Badge => 32,
        };
    }

    public static function fromDisplaySize(?int $displaySize): self
    {
        if ($displaySize === null) {
            return self::Badge;
        }

        if ($displaySize >= 256) {
            return self::Preview;
        }

        if ($displaySize >= 96) {
            return self::Hero;
        }

        return self::Badge;
    }
}

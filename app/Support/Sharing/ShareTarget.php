<?php

declare(strict_types=1);

namespace App\Support\Sharing;

enum ShareTarget: string
{
    case Copy = 'copy';
    case Native = 'native';
    case Facebook = 'facebook';
    case WhatsApp = 'whatsapp';
    case X = 'x';
    case Telegram = 'telegram';

    public function isExternal(): bool
    {
        return match ($this) {
            self::Facebook, self::WhatsApp, self::X, self::Telegram => true,
            self::Copy, self::Native => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function externalCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $target): bool => $target->isExternal(),
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Sharing;

enum ShareTarget: string
{
    case Copy = 'copy';
    case Facebook = 'facebook';
    case WhatsApp = 'whatsapp';
    case Instagram = 'instagram';
    case X = 'x';
    case Telegram = 'telegram';

    public function isExternal(): bool
    {
        return match ($this) {
            self::Facebook, self::WhatsApp, self::X, self::Telegram => true,
            self::Copy, self::Instagram => false,
        };
    }

    /**
     * Instagram has no web share intent; the menu copies a tracked link instead.
     */
    public function usesClipboard(): bool
    {
        return match ($this) {
            self::Copy, self::Instagram => true,
            default => false,
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

    /**
     * Platform rows shown in the share menu (excludes the generic copy action).
     *
     * @return list<self>
     */
    public static function menuPlatformCases(): array
    {
        return [
            self::Facebook,
            self::WhatsApp,
            self::Instagram,
            self::X,
            self::Telegram,
        ];
    }
}

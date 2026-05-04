<?php

declare(strict_types=1);

namespace App\Enums;

enum AvatarSource: string
{
    case Generated = 'generated';
    case Uploaded = 'uploaded';
    case Gravatar = 'gravatar';
    case Google = 'google';
    case Facebook = 'facebook';

    /**
     * @return list<self>
     */
    public static function remoteCacheable(): array
    {
        return [self::Gravatar, self::Google, self::Facebook];
    }

    public function usesRemoteCache(): bool
    {
        return in_array($this, self::remoteCacheable(), true);
    }
}

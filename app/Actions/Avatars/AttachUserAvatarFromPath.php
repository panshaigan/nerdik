<?php

declare(strict_types=1);

namespace App\Actions\Avatars;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

final class AttachUserAvatarFromPath
{
    private const int AVATAR_SIZE = 512;

    public function __invoke(User $user, string $absolutePath): void
    {
        $user->clearMediaCollection('avatar');
        $user->addMedia($absolutePath)
            ->withCustomProperties([
                'width' => self::AVATAR_SIZE,
                'height' => self::AVATAR_SIZE,
            ])
            ->toMediaCollection('avatar');

        self::deleteLegacyAvatarFile($user);
    }

    public static function deleteLegacyAvatarFile(User $user): void
    {
        $path = 'avatars/'.$user->id.'.webp';

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public static function deleteLegacyAvatarFileForUserId(int $userId): void
    {
        $path = 'avatars/'.$userId.'.webp';

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}

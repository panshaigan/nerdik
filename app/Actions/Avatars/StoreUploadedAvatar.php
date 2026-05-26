<?php

declare(strict_types=1);

namespace App\Actions\Avatars;

use App\Actions\Images\StoreCroppedPublicImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class StoreUploadedAvatar
{
    private const int AVATAR_SIZE = 512;

    public function __construct(
        private StoreCroppedPublicImage $storeCroppedPublicImage,
    ) {}

    /**
     * Writes `avatars/{user_id}.webp` on the public disk and returns the relative path.
     */
    public function __invoke(User $user, TemporaryUploadedFile|UploadedFile $file): string
    {
        $relativePath = 'avatars/'.$user->id.'.webp';

        return ($this->storeCroppedPublicImage)(
            $relativePath,
            $file,
            self::AVATAR_SIZE,
            self::AVATAR_SIZE,
        );
    }
}

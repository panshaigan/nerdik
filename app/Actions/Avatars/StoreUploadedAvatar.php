<?php

declare(strict_types=1);

namespace App\Actions\Avatars;

use App\Actions\Images\StoreCroppedPublicImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class StoreUploadedAvatar
{
    private const int AVATAR_SIZE = 512;

    public function __construct(
        private StoreCroppedPublicImage $storeCroppedPublicImage,
        private AttachUserAvatarFromPath $attachUserAvatarFromPath,
    ) {}

    public function __invoke(User $user, TemporaryUploadedFile|UploadedFile $file): void
    {
        $tempRelativePath = 'media/temp/avatars/temp-'.$user->id.'-'.uniqid('', true).'.webp';

        ($this->storeCroppedPublicImage)(
            $tempRelativePath,
            $file,
            self::AVATAR_SIZE,
            self::AVATAR_SIZE,
        );

        $absolutePath = Storage::disk('public')->path($tempRelativePath);

        ($this->attachUserAvatarFromPath)($user, $absolutePath);

        Storage::disk('public')->delete($tempRelativePath);
    }
}

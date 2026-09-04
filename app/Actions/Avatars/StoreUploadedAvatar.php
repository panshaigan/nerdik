<?php

declare(strict_types=1);

namespace App\Actions\Avatars;

use App\Actions\Images\AttachSourceImageFromPath;
use App\Actions\Images\StoreCroppedPublicImage;
use App\Actions\Images\StoreSourcePublicImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class StoreUploadedAvatar
{
    private const int AVATAR_SIZE = 512;

    public function __construct(
        private StoreCroppedPublicImage $storeCroppedPublicImage,
        private StoreSourcePublicImage $storeSourcePublicImage,
        private AttachUserAvatarFromPath $attachUserAvatarFromPath,
        private AttachSourceImageFromPath $attachSourceImageFromPath,
    ) {}

    public function __invoke(
        User $user,
        TemporaryUploadedFile|UploadedFile $file,
        TemporaryUploadedFile|UploadedFile|null $sourceFile = null,
    ): void {
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

        if ($sourceFile !== null) {
            $sourceTempRelativePath = 'media/temp/avatars/source-'.$user->id.'-'.uniqid('', true).'.webp';

            ($this->storeSourcePublicImage)($sourceTempRelativePath, $sourceFile);

            $sourceAbsolutePath = Storage::disk('public')->path($sourceTempRelativePath);
            ($this->attachSourceImageFromPath)($user, $sourceAbsolutePath);
            Storage::disk('public')->delete($sourceTempRelativePath);
        }
    }
}

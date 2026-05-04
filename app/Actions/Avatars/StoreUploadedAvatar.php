<?php

declare(strict_types=1);

namespace App\Actions\Avatars;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class StoreUploadedAvatar
{
    private const int AVATAR_SIZE = 512;

    private const int WEBP_QUALITY = 85;

    public function __construct(
        private ImageManager $manager,
    ) {}

    /**
     * Writes `avatars/{user_id}.webp` on the public disk and returns the relative path.
     */
    public function __invoke(User $user, TemporaryUploadedFile|UploadedFile $file): string
    {
        $relativePath = 'avatars/'.$user->id.'.webp';

        $image = $this->manager->read($file->getRealPath())->cover(self::AVATAR_SIZE, self::AVATAR_SIZE);
        $encoded = $image->toWebp(self::WEBP_QUALITY);

        Storage::disk('public')->put($relativePath, $encoded->toString(), [
            'visibility' => 'public',
        ]);

        return $relativePath;
    }
}

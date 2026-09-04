<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Actions\Images\StoreCroppedPublicImage;
use App\Actions\Images\StoreSourcePublicImage;
use App\Models\User;
use App\Support\Media\UserGalleryCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class StoreUserGalleryImage
{
    public function __construct(
        private StoreCroppedPublicImage $storeCroppedPublicImage,
        private StoreSourcePublicImage $storeSourcePublicImage,
        private AttachOptimizedImage $attachOptimizedImage,
    ) {}

    public function __invoke(
        User $user,
        TemporaryUploadedFile|UploadedFile $file,
        int $width,
        int $height,
        TemporaryUploadedFile|UploadedFile|null $sourceFile = null,
    ): Media {
        $tempRelativePath = 'media/temp/gallery/temp-'.$user->id.'-'.uniqid('', true).'.webp';

        ($this->storeCroppedPublicImage)(
            $tempRelativePath,
            $file,
            $width,
            $height,
        );

        $absolutePath = Storage::disk('public')->path($tempRelativePath);

        $media = ($this->attachOptimizedImage)(
            $user,
            $absolutePath,
            UserGalleryCatalog::COLLECTION,
            [
                'width' => $width,
                'height' => $height,
            ],
            preserveOriginal: false,
        );

        Storage::disk('public')->delete($tempRelativePath);

        if ($sourceFile !== null) {
            $sourceRelativePath = UserGalleryCatalog::sourceRelativePath((int) $media->id);

            ($this->storeSourcePublicImage)($sourceRelativePath, $sourceFile);

            $media->setCustomProperty(UserGalleryCatalog::SOURCE_PATH_PROPERTY, $sourceRelativePath);
            $media->save();
        }

        return $media;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Actions\Images\StoreCroppedPublicImage;
use App\Models\User;
use App\Support\Media\UserGalleryCatalog;
use App\Support\Performance\PersistenceTiming;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class StoreUserGalleryImage
{
    public function __construct(
        private StoreCroppedPublicImage $storeCroppedPublicImage,
        private AttachOptimizedImage $attachOptimizedImage,
        private StageUserGallerySourceImage $stageUserGallerySourceImage,
    ) {}

    public function __invoke(
        User $user,
        TemporaryUploadedFile|UploadedFile $file,
        int $width,
        int $height,
        TemporaryUploadedFile|UploadedFile|null $sourceFile = null,
        ?PersistenceTiming $timing = null,
    ): Media {
        $tempRelativePath = 'media/temp/gallery/temp-'.$user->id.'-'.uniqid('', true).'.webp';

        ($this->storeCroppedPublicImage)(
            $tempRelativePath,
            $file,
            $width,
            $height,
        );
        $timing?->checkpoint('image_crop_encode');

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
        $timing?->checkpoint('media_attachment');

        if ($sourceFile !== null) {
            ($this->stageUserGallerySourceImage)($media, $sourceFile);
            $timing?->checkpoint('source_staging');
        }

        return $media;
    }
}

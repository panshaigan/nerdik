<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Actions\Images\StoreCroppedPublicImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\HasMedia;

final class AttachEntityLogoCrop
{
    public function __construct(
        private StoreCroppedPublicImage $storeCroppedPublicImage,
    ) {}

    public function __invoke(
        HasMedia $model,
        TemporaryUploadedFile|UploadedFile $file,
        int $width,
        int $height,
    ): void {
        $tempRelativePath = 'media/temp/entity-logos/temp-'.uniqid('', true).'.webp';

        ($this->storeCroppedPublicImage)(
            $tempRelativePath,
            $file,
            $width,
            $height,
        );

        $absolutePath = Storage::disk('public')->path($tempRelativePath);

        $model->clearMediaCollection('logo');
        $model->addMedia($absolutePath)
            ->withCustomProperties([
                'width' => $width,
                'height' => $height,
            ])
            ->toMediaCollection('logo');

        Storage::disk('public')->delete($tempRelativePath);
    }
}

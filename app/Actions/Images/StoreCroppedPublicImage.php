<?php

declare(strict_types=1);

namespace App\Actions\Images;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class StoreCroppedPublicImage
{
    private const int WEBP_QUALITY = 85;

    public function __construct(
        private ImageManager $manager,
    ) {}

    /**
     * Writes the image to the public disk and returns the relative path.
     */
    public function __invoke(
        string $relativePath,
        TemporaryUploadedFile|UploadedFile $file,
        int $width,
        int $height,
    ): string {
        $image = $this->manager->read($file->getRealPath())->cover($width, $height);
        $encoded = $image->toWebp(self::WEBP_QUALITY);

        Storage::disk('public')->put($relativePath, $encoded->toString(), [
            'visibility' => 'public',
        ]);

        return $relativePath;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Images;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class StoreSourcePublicImage
{
    private const int WEBP_QUALITY = 85;

    private const int MAX_EDGE = 4096;

    public function __construct(
        private ImageManager $manager,
    ) {}

    /**
     * Writes the uncropped source image as WebP to the public disk and returns the relative path.
     */
    public function __invoke(
        string $relativePath,
        TemporaryUploadedFile|UploadedFile|string $file,
    ): string {
        $sourcePath = is_string($file) ? $file : $file->getRealPath();

        if ($sourcePath === false) {
            throw new \RuntimeException('Unable to resolve the source image path.');
        }

        $image = $this->manager->read($sourcePath);
        $image->scaleDown(self::MAX_EDGE, self::MAX_EDGE);
        $encoded = $image->toWebp(self::WEBP_QUALITY);

        $written = Storage::disk('public')->put($relativePath, $encoded->toString(), [
            'visibility' => 'public',
        ]);

        if ($written !== true) {
            throw new \RuntimeException("Failed to write source image to public disk at [{$relativePath}].");
        }

        return $relativePath;
    }
}

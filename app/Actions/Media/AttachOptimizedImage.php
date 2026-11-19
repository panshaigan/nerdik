<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\Media;
use App\Support\Media\SimpleMediaFileNamer;
use Illuminate\Support\Facades\File;
use Spatie\MediaLibrary\HasMedia;

final class AttachOptimizedImage
{
    public function __construct(
        private readonly NormalizeMediaOriginal $normalizeMediaOriginal,
    ) {}

    /**
     * @param  array<string, mixed>  $extraCustomProperties
     */
    public function __invoke(
        HasMedia $model,
        string $absolutePath,
        string $collection = 'images',
        array $extraCustomProperties = [],
        bool $preserveOriginal = true,
    ): Media {
        $normalized = ($this->normalizeMediaOriginal)($absolutePath);

        $adder = $model->addMedia($normalized['path'])
            ->usingFileName(SimpleMediaFileNamer::ORIGINAL_FILE_NAME);

        // Keep seed/public sources when we did not rewrite into a temp file.
        if ($preserveOriginal && ! $normalized['temporary']) {
            $adder->preservingOriginal();
        }

        $media = $adder
            ->withCustomProperties(array_merge([
                'width' => $normalized['width'],
                'height' => $normalized['height'],
            ], $extraCustomProperties))
            ->toMediaCollection($collection);

        if ($normalized['temporary'] && File::isFile($normalized['path'])) {
            File::delete($normalized['path']);
        }

        return $media;
    }
}

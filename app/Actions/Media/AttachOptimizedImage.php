<?php

declare(strict_types=1);

namespace App\Actions\Media;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class AttachOptimizedImage
{
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
        $imageSize = @getimagesize($absolutePath);

        $adder = $model->addMedia($absolutePath);

        if ($preserveOriginal) {
            $adder->preservingOriginal();
        }

        return $adder
            ->withCustomProperties(array_merge([
                'width' => $imageSize !== false ? $imageSize[0] : null,
                'height' => $imageSize !== false ? $imageSize[1] : null,
            ], $extraCustomProperties))
            ->toMediaCollection($collection);
    }
}
